{{-- Homework and tests for one student. Students get links to do them; parents see the status. Expects $assignments, $submissions, $quizzes, $quizAttempts and $ownProfile. --}}
<div class="flex flex-col gap-4">
    @if ($ownProfile)
        <x-button variant="brand" icon="notebook-pen" :href="route('school.portal.classwork')" class="self-start">Open My work</x-button>
    @endif

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Assignments</h2>
        @forelse ($assignments as $assignment)
            @php($submission = $submissions->get($assignment->id))
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                <div class="min-w-0 flex-1">
                    @if ($ownProfile)
                        <a href="{{ route('school.portal.assignments.show', $assignment) }}" class="font-medium hover:text-brand-600">{{ $assignment->title }}</a>
                    @else
                        <div class="font-medium">{{ $assignment->title }}</div>
                    @endif
                    <div class="text-sm text-muted">{{ $assignment->classSubject->subject->name }} · due {{ $assignment->due_at->format('D j M') }}</div>
                </div>
                @if ($submission?->marked_at)
                    <x-badge tone="naija">{{ $submission->score !== null ? rtrim(rtrim(number_format($submission->score, 1), '0'), '.').($assignment->max_score ? '/'.$assignment->max_score : '') : 'Marked' }}</x-badge>
                @elseif ($submission)
                    <x-badge tone="sky">Handed in</x-badge>
                @elseif ($assignment->isPastDue())
                    <x-badge tone="danger">Missing</x-badge>
                @else
                    <x-badge tone="gold">To do</x-badge>
                @endif
            </div>
        @empty
            <p class="m-0 px-5 py-4 text-sm text-muted">No assignments yet.</p>
        @endforelse
    </x-card>

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Quizzes & exams</h2>
        @forelse ($quizzes as $quiz)
            @php($attempt = $quizAttempts->get($quiz->id))
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                <div class="min-w-0 flex-1">
                    @if ($ownProfile)
                        <a href="{{ route('school.portal.quizzes.show', $quiz) }}" class="font-medium hover:text-brand-600">{{ $quiz->title }}</a>
                    @else
                        <div class="font-medium">{{ $quiz->title }}</div>
                    @endif
                    <div class="text-sm text-muted">{{ $quiz->classSubject->subject->name }} · {{ $quiz->kind->label() }}</div>
                </div>
                @if ($attempt?->submitted_at)
                    <x-badge tone="naija">{{ $attempt->percent() }}%</x-badge>
                @elseif ($quiz->isOpen())
                    <x-badge tone="brand">Open now</x-badge>
                @else
                    <x-badge>{{ $quiz->hasClosed() ? 'Missed' : 'Coming up' }}</x-badge>
                @endif
            </div>
        @empty
            <p class="m-0 px-5 py-4 text-sm text-muted">No quizzes yet.</p>
        @endforelse
    </x-card>
</div>
