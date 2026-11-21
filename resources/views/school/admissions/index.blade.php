@php
    use App\Enums\ApplicantStatus;
    $formFields = ['class_level_id', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'religion', 'state_of_origin', 'previous_school', 'address', 'guardian_title', 'guardian_first_name', 'guardian_last_name', 'guardian_relationship', 'guardian_phone', 'guardian_email', 'guardian_occupation'];
    $publicLink = route('apply.show', ['school' => $currentSchool->slug]);
@endphp

<x-layouts.app title="Admissions">
    <x-page-header kicker="People" title="Admissions" :description="$session ? 'Applications for the '.$session->name.' session.' : 'Start a session before taking applications.'">
        <x-slot:actions>
            <form method="POST" action="{{ route('school.admissions.form') }}">
                @csrf
                <x-button variant="secondary" :icon="$isOpen ? 'lock' : 'lock-open'">{{ $isOpen ? 'Close online form' : 'Open online form' }}</x-button>
            </form>
            <x-button icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-application')">Record an application</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-white px-5 py-3.5 text-[15px]" x-data="{ copied: false }">
        <x-lucide-link class="size-5 flex-none text-brand-600" aria-hidden="true" />
        <span class="text-muted">Online form{{ $isOpen ? '' : ' (closed)' }}:</span>
        <a href="{{ $publicLink }}" target="_blank" rel="noopener" class="min-w-0 truncate font-mono text-sm font-semibold text-ink hover:text-brand-600">{{ $publicLink }}</a>
        <button type="button" class="ml-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-brand-600 hover:bg-brand-50" x-on:click="navigator.clipboard.writeText(@js($publicLink)); copied = true; setTimeout(() => copied = false, 2000)">
            <x-lucide-copy class="size-4" aria-hidden="true" /> <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
        </button>
    </div>

    <nav class="-mx-1 overflow-x-auto px-1" aria-label="Application stages">
        <div class="inline-flex min-w-full gap-1 rounded-2xl border border-line bg-white p-1.5 sm:min-w-0">
            @foreach ($stages as $key => $label)
                <a href="{{ route('school.admissions.index', ['stage' => $key]) }}" @class([
                    'flex flex-none items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold whitespace-nowrap transition',
                    'bg-ink text-white' => $stage === $key,
                    'text-muted hover:text-ink' => $stage !== $key,
                ]) @if ($stage === $key) aria-current="page" @endif>
                    {{ $label }}
                    <span @class(['rounded-full px-1.5 py-0.5 text-[11px]', 'bg-white/20' => $stage === $key, 'bg-chip' => $stage !== $key])>{{ $stageCounts[$key] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4" role="search">
            <input type="hidden" name="stage" value="{{ $stage }}">
            <div class="flex min-w-60 flex-1 flex-col gap-1.5">
                <label for="q" class="text-sm font-semibold">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Name, application no. or phone" class="field">
            </div>
            <x-button variant="secondary" icon="search">Search</x-button>
        </form>

        @if ($applicants->isEmpty())
            <x-empty-state icon="user-plus" :title="$search !== '' ? 'No applications match' : 'Nothing here yet'" :body="$stage === 'new' ? 'New applications from the online form, or recorded at the office, appear here.' : null" />
        @else
            <form method="POST" action="{{ route('school.admissions.bulk', ['stage' => $stage]) }}" x-data="{ selected: [], action: '' }">
                @csrf
                <div x-show="selected.length" x-cloak class="flex flex-wrap items-end gap-3 border-b border-line bg-brand-50/60 px-5 py-3">
                    <span class="self-center text-sm font-semibold"><span x-text="selected.length"></span> selected</span>
                    <div class="flex flex-col gap-1">
                        <label for="bulk-action" class="text-xs font-semibold">Move to</label>
                        <select id="bulk-action" name="action" class="field bg-white py-2" x-model="action" required>
                            <option value="">Choose…</option>
                            @foreach ([ApplicantStatus::ExamScheduled, ApplicantStatus::Offered, ApplicantStatus::Rejected] as $target)
                                <option value="{{ $target->value }}">{{ $target->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1" x-show="action === @js(ApplicantStatus::ExamScheduled->value)">
                        <label for="bulk-exam" class="text-xs font-semibold">Exam date and time</label>
                        <input id="bulk-exam" name="exam_at" type="datetime-local" class="field bg-white py-2">
                    </div>
                    <x-button size="sm" icon="check" class="mb-0.5">Apply</x-button>
                </div>
                @if ($errors->hasAny(['applicants', 'action', 'exam_at']))
                    <p class="m-0 border-b border-line bg-danger-50 px-5 py-2.5 text-sm font-medium text-danger" role="alert">{{ $errors->first('applicants') ?: $errors->first('action') ?: $errors->first('exam_at') }}</p>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="w-10 py-3 pl-5"><span class="sr-only">Select</span></th>
                                <th scope="col" class="px-3 py-3 font-semibold">Child</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Class</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Parent</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Applied</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Exam</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applicants as $applicant)
                                <tr class="border-t border-line hover:bg-canvas">
                                    <td class="py-3 pl-5">
                                        <input type="checkbox" name="applicants[]" value="{{ $applicant->ulid }}" x-model="selected" class="size-4 rounded border-line accent-brand-600" aria-label="Select {{ $applicant->fullName() }}">
                                    </td>
                                    <td class="px-3 py-3">
                                        <a href="{{ route('school.admissions.show', $applicant) }}" class="font-medium hover:text-brand-600">{{ $applicant->registerName() }}</a>
                                        <span class="block font-mono text-xs text-muted">{{ $applicant->application_no }} · {{ $applicant->gender->label() }}{{ $applicant->date_of_birth ? ' · '.$applicant->date_of_birth->age.' yrs' : '' }}</span>
                                    </td>
                                    <td class="px-3 py-3">{{ $applicant->classLevel->name }}</td>
                                    <td class="px-3 py-3 text-sm">
                                        {{ $applicant->guardianName() }}
                                        <span class="block font-mono text-xs text-muted">{{ $applicant->guardian_phone }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-muted">
                                        {{ $applicant->created_at->format('d/m/Y') }}
                                        <span class="block text-xs">{{ $applicant->source === 'online' ? 'Online' : 'At the office' }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-sm">
                                        @if ($applicant->exam_at)
                                            {{ $applicant->exam_at->format('D j M, g:ia') }}
                                            @if ($applicant->exam_score !== null)<span class="block text-xs font-semibold text-brand-600">Score {{ rtrim(rtrim(number_format($applicant->exam_score, 1), '0'), '.') }}</span>@endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3"><x-badge :tone="$applicant->status->tone()">{{ $applicant->status->label() }}</x-badge></td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('school.admissions.show', $applicant) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Open <x-lucide-chevron-right class="size-4" aria-hidden="true" /></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            @if ($applicants->hasPages())
                <div class="border-t border-line px-5 py-3">{{ $applicants->links() }}</div>
            @endif
        @endif
    </x-card>

    <x-drawer name="new-application" title="Record an application" description="For a family applying at the office. They get an application number like the online form." width="sm:max-w-2xl" :show="$errors->hasAny($formFields)">
        <form method="POST" action="{{ route('school.admissions.store') }}" class="flex flex-col gap-5" id="new-application-form">
            @csrf
            @include('admissions._application-fields')
            <div class="flex flex-col gap-1.5">
                <label for="notes" class="text-sm font-semibold">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="field resize-y" placeholder="Anything the admissions team should know">{{ old('notes') }}</textarea>
            </div>
        </form>
        <x-slot:footer>
            <x-button variant="brand" icon="check" form="new-application-form">Save application</x-button>
        </x-slot:footer>
    </x-drawer>
</x-layouts.app>
