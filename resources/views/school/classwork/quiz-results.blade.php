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
            <form method="POST" action="{{ route('school.classwork.quizzes.scorebook', $quiz) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="component_id" class="text-sm font-semibold">Use these marks as</label>
                    <select id="component_id" name="component_id" class="field w-56 py-2">
                        @foreach ($components as $component)<option value="{{ $component->id }}">{{ $component->name }} (out of {{ $component->max_score }})</option>@endforeach
                    </select>
                </div>
                <x-button variant="secondary" icon="file-pen-line" onclick="return confirm('Replace these students’ scores for that component with their quiz percentage?')">Send to scorebook</x-button>
                <p class="m-0 text-sm text-muted">Each student’s percentage becomes their score, e.g. 75% → 15 out of 20.</p>
            </form>
            @error('component')<p class="m-0 mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
        </x-card>
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
