@php
    $todo = $assignments->reject(fn ($assignment) => $submissions->has($assignment->id));
    $openQuizzes = $quizzes->filter(fn ($quiz) => $quiz->isOpen() && ! $attempts->get($quiz->id)?->submitted_at);
@endphp

<x-layouts.app title="My work">
    <x-page-header kicker="My work" :title="'Hello, '.$student->first_name" :description="$todo->count().' '.str('assignment')->plural($todo->count()).' to hand in · '.$openQuizzes->count().' '.str('quiz')->plural($openQuizzes->count()).' open now'" />

    <x-tabs :tabs="['quizzes' => ['label' => 'Quizzes & exams', 'count' => $openQuizzes->count()], 'todo' => ['label' => 'To hand in', 'count' => $todo->count()], 'done' => ['label' => 'Handed in', 'count' => $submissions->count()]]" label="My work">
        <x-tab name="quizzes">
            <x-card :padding="false">
                @forelse ($quizzes as $quiz)
                    @php($attempt = $attempts->get($quiz->id))
                    <a href="{{ route('school.portal.quizzes.show', $quiz) }}" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4 last:border-b-0 hover:bg-canvas">
                        <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-lucide-monitor-check class="size-5" aria-hidden="true" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $quiz->title }}</div>
                            <div class="text-sm text-muted">{{ $quiz->classSubject->subject->name }} · {{ $quiz->kind->label() }}{{ $quiz->duration_minutes ? ' · '.$quiz->duration_minutes.' min' : '' }}{{ $quiz->closes_at ? ' · closes '.$quiz->closes_at->format('D j M, g:ia') : '' }}</div>
                        </div>
                        @if ($attempt?->submitted_at)
                            <x-badge tone="naija">{{ $attempt->percent() }}%</x-badge>
                        @elseif ($attempt)
                            <x-badge tone="gold">In progress</x-badge>
                        @elseif ($quiz->isOpen())
                            <x-badge tone="brand">Start</x-badge>
                        @else
                            <x-badge>{{ $quiz->hasClosed() ? 'Closed' : 'Opens '.$quiz->opens_at?->format('j M') }}</x-badge>
                        @endif
                    </a>
                @empty
                    <x-empty-state icon="monitor-check" title="No quizzes yet" body="When your teachers set a quiz or a CBT exam, it appears here." />
                @endforelse
            </x-card>
        </x-tab>
        <x-tab name="todo">
            <x-card :padding="false">
                @forelse ($todo as $assignment)
                    <a href="{{ route('school.portal.assignments.show', $assignment) }}" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4 last:border-b-0 hover:bg-canvas">
                        <span @class(['grid size-10 place-items-center rounded-xl', 'bg-danger-50 text-danger' => $assignment->isPastDue(), 'bg-gold-50 text-gold' => ! $assignment->isPastDue()])><x-lucide-notebook-pen class="size-5" aria-hidden="true" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $assignment->title }}</div>
                            <div class="text-sm text-muted">{{ $assignment->classSubject->subject->name }} · {{ $assignment->isPastDue() ? 'was due' : 'due' }} {{ $assignment->due_at->format('D j M, g:ia') }}</div>
                        </div>
                        <x-badge :tone="$assignment->isPastDue() ? 'danger' : 'gold'">{{ $assignment->isPastDue() ? 'Late' : $assignment->due_at->diffForHumans(['parts' => 1]) }}</x-badge>
                    </a>
                @empty
                    <x-empty-state icon="party-popper" title="All caught up" body="Nothing to hand in right now." />
                @endforelse
            </x-card>
        </x-tab>
        <x-tab name="done">
            <x-card :padding="false">
                @forelse ($assignments->filter(fn ($assignment) => $submissions->has($assignment->id)) as $assignment)
                    @php($submission = $submissions[$assignment->id])
                    <a href="{{ route('school.portal.assignments.show', $assignment) }}" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4 last:border-b-0 hover:bg-canvas">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $assignment->title }}</div>
                            <div class="text-sm text-muted">{{ $assignment->classSubject->subject->name }} · handed in {{ $submission->submitted_at->format('j M') }}</div>
                        </div>
                        @if ($submission->marked_at)
                            <x-badge tone="naija">{{ $submission->score !== null ? rtrim(rtrim(number_format($submission->score, 1), '0'), '.').($assignment->max_score ? '/'.$assignment->max_score : '') : 'Marked' }}</x-badge>
                        @else
                            <x-badge>Waiting for mark</x-badge>
                        @endif
                    </a>
                @empty
                    <x-empty-state icon="inbox" title="Nothing handed in yet" />
                @endforelse
            </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
