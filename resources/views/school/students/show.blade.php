<x-layouts.app :title="$student->fullName()">
    @php
        $enrolment = $student->currentEnrolment;
        $arm = $enrolment?->classArm;
    @endphp

    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.students.index') }}" class="hover:text-ink">Students</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $student->fullName() }}</span>
    </div>

    <x-credentials />

    <div class="flex flex-wrap items-center gap-5 rounded-3xl border border-line bg-white p-6" data-enter>
        @can('update', $student)
            <x-photo-upload :person="$student" :action="route('school.students.photo', $student)" :name="$student->first_name" />
        @else
            <x-avatar :person="$student" class="size-20 rounded-3xl text-2xl" />
        @endcan
        <div class="min-w-0 flex-1">
            <h1 class="m-0 text-[28px] leading-tight font-bold tracking-[-0.03em]">{{ $student->fullName() }}</h1>
            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[15px] text-muted">
                <span class="font-mono">{{ $student->admission_no }}</span>
                <span aria-hidden="true">·</span>
                @if ($arm)
                    <x-badge tone="brand">{{ $arm->displayName() }}{{ $arm->stream ? ' · '.$arm->stream->name : '' }}</x-badge>
                @endif
                <x-badge :tone="$student->status->tone()">{{ $student->status->label() }}</x-badge>
            </div>
        </div>
        @can('update', $student)
            <div class="flex flex-wrap gap-2">
                <x-button variant="secondary" icon="pencil" :href="route('school.students.edit', $student)">Edit</x-button>
                @if ($student->user)
                    <form method="POST" action="{{ route('school.students.password', $student) }}" data-confirm="Give {{ $student->first_name }} a new password? The old one will stop working." data-confirm-title="Reset password?" data-confirm-action="Reset" data-confirm-tone="brand">
                        @csrf
                        <x-button variant="ghost" icon="key-round">Reset login</x-button>
                    </form>
                @endif
            </div>
        @endcan
    </div>

    <x-tabs :tabs="array_filter(['overview' => 'Overview', 'results' => ['label' => 'Results', 'count' => $resultTerms->count()], 'fees' => $fees ? ['label' => 'Fees', 'count' => $fees['invoices']->where('status', \App\Enums\InvoiceStatus::Unpaid)->count() + $fees['invoices']->where('status', \App\Enums\InvoiceStatus::PartPaid)->count()] : null, 'subjects' => ['label' => 'Subjects', 'count' => $arm?->classSubjects->count() ?? 0], 'parents' => ['label' => 'Parents', 'count' => $student->guardians->count()], 'history' => 'Class history', 'login' => 'Portal login'])" label="Student record">
        <x-tab name="overview" class="max-w-4xl">
            <x-card>
                <h2 class="m-0 mb-4 text-lg font-semibold">Personal details</h2>
                <dl class="m-0 grid grid-cols-1 gap-x-8 gap-y-4 text-[15px] sm:grid-cols-2">
                    @foreach ([
                        'Gender' => $student->gender->label(),
                        'Date of birth' => $student->date_of_birth ? $student->date_of_birth->format('d/m/Y').' ('.$student->age().' years)' : null,
                        'State of origin' => trim(($student->state_of_origin ?? '').($student->lga ? ', '.$student->lga : '')) ?: null,
                        'Religion' => $student->religion,
                        'Blood group' => $student->blood_group,
                        'Genotype' => $student->genotype,
                        'Admitted' => $student->admitted_on?->format('d/m/Y'),
                        'Previous school' => $student->previous_school,
                    ] as $label => $value)
                        <div>
                            <dt class="text-sm text-muted">{{ $label }}</dt>
                            <dd class="m-0 mt-0.5 font-medium">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-muted">Home address</dt>
                        <dd class="m-0 mt-0.5 font-medium">{{ $student->address ?? '—' }}</dd>
                    </div>
                    @if ($student->medical_notes && auth()->user()->can('students.view-all'))
                        <div class="rounded-xl bg-danger-50 p-3 sm:col-span-2">
                            <dt class="flex items-center gap-1.5 text-sm font-semibold text-danger"><x-lucide-heart-pulse class="size-4" aria-hidden="true" /> Medical notes</dt>
                            <dd class="m-0 mt-1 text-[15px] text-ink">{{ $student->medical_notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </x-tab>

        <x-tab name="results" class="max-w-3xl">
            <x-card :padding="false">
                <h2 class="m-0 border-b border-line px-6 py-4 text-lg font-semibold">Results by term</h2>
                @forelse ($resultTerms as $resultTerm)
                    <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-3 last:border-b-0">
                        <span class="font-medium">{{ $resultTerm->name }} · {{ $resultTerm->academicSession->name }}</span>
                        <a data-native href="{{ route('school.results.report', [$student, 'term' => $resultTerm->id]) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Report card <x-lucide-arrow-right class="size-4" aria-hidden="true" /></a>
                    </div>
                @empty
                    <x-empty-state icon="file-text" title="No scores yet" />
                @endforelse
            </x-card>
        </x-tab>

        @if ($fees)
            <x-tab name="fees" class="max-w-4xl">
                @include('school.students._fees-tab')
            </x-tab>
        @endif

        <x-tab name="subjects">
            @if ($arm)
                <x-card :padding="false">
                    <div class="flex items-center justify-between border-b border-line px-6 py-4">
                        <div>
                            <h2 class="m-0 text-lg font-semibold">Subjects in {{ $arm->displayName() }}</h2>
                            <p class="m-0 text-sm text-muted">Form teacher: {{ $arm->formTeacher?->fullName() ?? 'not assigned' }}</p>
                        </div>
                        @if (auth()->user()->can('students.view-all') || in_array($arm->id, auth()->user()->staffRecord()?->classArmIds() ?? [], true))
                            <a href="{{ route('school.classes.show', $arm) }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Open class</a>
                        @endif
                    </div>
                    <ul class="m-0 grid list-none grid-cols-1 p-0 sm:grid-cols-2">
                        @foreach ($arm->classSubjects->sortBy('subject.name') as $classSubject)
                            <li class="flex items-center justify-between gap-3 border-b border-line px-6 py-3 text-[15px] sm:odd:border-r">
                                <span class="font-medium">{{ $classSubject->subject->name }}</span>
                                <span class="truncate text-sm text-muted">{{ $classSubject->teacher?->fullName() ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @else
                <x-card><x-empty-state icon="book-open" title="Not in a class this session" /></x-card>
            @endif
        </x-tab>

        <x-tab name="parents" class="max-w-3xl">
            <x-card :padding="false">
                <h2 class="m-0 border-b border-line px-6 py-4 text-lg font-semibold">Parents &amp; guardians</h2>
                @forelse ($student->guardians as $guardian)
                    <div class="flex flex-col gap-2 border-b border-line px-6 py-4 last:border-b-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold">{{ $guardian->fullName() }}</span>
                            <x-badge :tone="$guardian->pivot->is_primary ? 'brand' : 'neutral'">{{ $guardian->relationship->label() }}{{ $guardian->pivot->is_primary ? ' · main contact' : '' }}</x-badge>
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <a href="tel:{{ $guardian->phone }}" class="inline-flex items-center gap-1.5 text-brand-600 hover:text-ink"><x-lucide-phone class="size-3.5" aria-hidden="true" />{{ $guardian->phone }}</a>
                            @if ($guardian->email)
                                <a href="mailto:{{ $guardian->email }}" class="inline-flex items-center gap-1.5 text-brand-600 hover:text-ink"><x-lucide-mail class="size-3.5" aria-hidden="true" />{{ $guardian->email }}</a>
                            @endif
                        </div>
                        <div class="text-sm text-muted">
                            {{ $guardian->occupation ?? '' }}
                            @if ($guardian->user)
                                · <span class="text-naija">Has portal access</span>
                            @endif
                        </div>
                        @if ($guardian->user && $guardian->user->email && ! $guardian->user->last_login_at)
                            @can('users.manage')
                                <form method="POST" action="{{ route('school.members.invite', $guardian->user->ulid) }}">
                                    @csrf
                                    <x-button variant="soft" size="sm" icon="mail">Email {{ $guardian->first_name }} a sign-in link</x-button>
                                </form>
                            @endcan
                        @endif
                    </div>
                @empty
                    <x-empty-state icon="users" title="No guardian linked" />
                @endforelse
            </x-card>
        </x-tab>

        <x-tab name="history" class="max-w-3xl">
            <x-card :padding="false">
                <h2 class="m-0 border-b border-line px-6 py-4 text-lg font-semibold">Class history</h2>
                <ol class="m-0 list-none p-0">
                    @foreach ($student->enrolments as $history)
                        <li class="flex items-center justify-between gap-3 border-b border-line px-6 py-3 text-[15px] last:border-b-0">
                            <span class="font-medium">{{ $history->academicSession->name }}</span>
                            <span class="flex items-center gap-2">
                                {{ $history->classArm->displayName() }}
                                <x-badge>{{ $history->status->label() }}</x-badge>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </x-tab>

        <x-tab name="login" class="max-w-3xl">
            <x-card>
                <h2 class="m-0 text-lg font-semibold">Portal login</h2>
                @if ($student->user)
                    <p class="m-0 mt-2 text-[15px] text-muted">
                        Logs in with school code <b class="font-mono text-ink">{{ $currentSchool->short_code }}</b> and admission number <b class="font-mono text-ink">{{ $student->admission_no }}</b>.
                        @if ($student->user->last_login_at)
                            Last logged in {{ $student->user->last_login_at->diffForHumans() }}.
                        @else
                            Has not logged in yet.
                        @endif
                    </p>
                @else
                    <p class="m-0 mt-2 text-[15px] text-muted">No portal login yet.</p>
                @endif
            </x-card>
        </x-tab>
    </x-tabs>

    @if ($fees)
        @can('payments.record')
            @include('school.payments._record-drawer', ['methods' => $fees['methods'], 'suggestedAmount' => max(0, $fees['balance'])])
        @endcan
    @endif
</x-layouts.app>
