@php
    $done = $attempts->filter(fn ($attempt) => $attempt->submitted_at !== null);
    $average = $done->isNotEmpty() ? round($done->avg(fn ($attempt) => $attempt->percent())) : null;
@endphp

<x-layouts.app :title="$quiz->title.' results'">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.classwork.index') }}" class="hover:text-ink">Classwork</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <a href="{{ route('school.classwork.quizzes.edit', $quiz) }}" class="hover:text-ink">{{ $quiz->title }}</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Results</span>
    </div>

    <x-page-header :kicker="$quiz->classSubject->classArm->displayName().' · '.$quiz->classSubject->subject->name" :title="$quiz->title.' results'" :description="$done->count().' of '.$students->count().' students done'.($average !== null ? ' · class average '.$average.'%' : '')" />

    @if ($done->isNotEmpty())
        <x-card>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1.5">
                    <label for="component_id_preview" class="text-sm font-semibold">Use these marks as</label>
                    <select id="component_id_preview" class="field w-56 py-2" form="scorebook-form" name="component_id">
                        @foreach ($components as $component)<option value="{{ $component->id }}" @selected((string) old('component_id') === (string) $component->id)>{{ $component->name }} (out of {{ $component->max_score }})</option>@endforeach
                    </select>
                </div>
                <x-button variant="secondary" icon="file-pen-line" type="button" x-data x-on:click="$dispatch('open-modal', 'send-scorebook')">Send to scorebook</x-button>
                <p class="m-0 text-sm text-muted">Each student’s percentage becomes their score, e.g. 75% → 15 out of 20.</p>
            </div>
            @error('component')<p class="m-0 mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
        </x-card>

        <x-modal name="send-scorebook" title="Send to scorebook?" description="This replaces any scores already entered for the chosen component for students who finished this quiz." :show="$errors->has('component')">
            <form id="scorebook-form" method="POST" action="{{ route('school.classwork.quizzes.scorebook', $quiz) }}" class="flex flex-col gap-4">
                @csrf
                <p class="m-0 text-[15px] text-muted">
                    {{ $done->count() }} {{ \Illuminate\Support\Str::plural('student', $done->count()) }} will get a score from their quiz percentage.
                    Students who have not submitted are left alone.
                </p>
                <div class="flex flex-wrap gap-2">
                    <x-button variant="brand" icon="file-pen-line">Replace scores</x-button>
                    <x-button variant="ghost" type="button" x-data x-on:click="$dispatch('close-modal', 'send-scorebook')">Cancel</x-button>
                </div>
            </form>
        </x-modal>
    @endif

    <x-card :padding="false">
        <table class="w-full border-collapse text-left text-[15px]">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3 font-semibold">Student</th>
                    <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                    <th scope="col" class="px-3 py-3 text-right font-semibold">Score</th>
                    <th scope="col" class="px-5 py-3 text-right font-semibold">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    @php($attempt = $attempts->get($student->id))
                    <tr class="border-t border-line">
                        <td class="px-5 py-3 font-medium">{{ $student->registerName() }}</td>
                        <td class="px-3 py-3">
                            @if (! $attempt)<x-badge>Not started</x-badge>
                            @elseif (! $attempt->submitted_at)<x-badge tone="gold">In progress</x-badge>
                            @else<x-badge tone="naija">Done {{ $attempt->submitted_at->format('j M, g:ia') }}</x-badge>@endif
                        </td>
                        <td class="px-3 py-3 text-right">{{ $attempt?->submitted_at ? rtrim(rtrim(number_format($attempt->score, 1), '0'), '.').' / '.$attempt->max_score : '—' }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ $attempt?->percent() !== null ? $attempt->percent().'%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
</x-layouts.app>
