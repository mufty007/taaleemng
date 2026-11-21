<x-layouts.app :title="$assignment->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.classwork.index', ['class_subject' => $assignment->class_subject_id]) }}#assignments" class="hover:text-ink">Classwork</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $assignment->title }}</span>
    </div>

    <x-page-header :kicker="$assignment->classSubject->classArm->displayName().' · '.$assignment->classSubject->subject->name" :title="$assignment->title" :description="'Due '.$assignment->due_at->format('l j F, g:ia').' · '.$submissions->count().' of '.$students->count().' handed in'.($assignment->max_score ? ' · marked out of '.$assignment->max_score : '')" />

    <x-tabs :tabs="['work' => ['label' => 'Handed in', 'count' => $submissions->count()], 'missing' => ['label' => 'Not yet', 'count' => $students->count() - $submissions->count()], 'brief' => 'Instructions']" label="Assignment">
        <x-tab name="work">
            <div class="flex flex-col gap-3">
                @forelse ($students->filter(fn ($student) => $submissions->has($student->id)) as $student)
                    @php($submission = $submissions[$student->id])
                    <x-card>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $student->registerName() }}</span>
                            <span class="text-sm text-muted">· {{ $submission->submitted_at->format('j M, g:ia') }}</span>
                            @if ($submission->submitted_at->gt($assignment->due_at))<x-badge tone="gold">Late</x-badge>@endif
                            @if ($submission->marked_at)<x-badge tone="naija" class="ml-auto">Marked {{ $submission->score !== null ? rtrim(rtrim(number_format($submission->score, 1), '0'), '.').($assignment->max_score ? '/'.$assignment->max_score : '') : '' }}</x-badge>@endif
                        </div>
                        <div class="mt-3 rounded-xl bg-canvas px-4 py-3 text-[15px] whitespace-pre-line">{{ $submission->body }}</div>
                        <form method="POST" action="{{ route('school.classwork.submissions.mark', $submission) }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            @method('PATCH')
                            <div class="flex w-28 flex-col gap-1.5">
                                <label for="score-{{ $submission->id }}" class="text-sm font-semibold">Score</label>
                                <input id="score-{{ $submission->id }}" name="score" type="number" step="0.5" min="0" max="{{ $assignment->max_score ?? 100 }}" value="{{ $submission->score }}" class="field py-2">
                            </div>
                            <div class="flex min-w-60 flex-1 flex-col gap-1.5">
                                <label for="feedback-{{ $submission->id }}" class="text-sm font-semibold">Feedback</label>
                                <input id="feedback-{{ $submission->id }}" name="feedback" type="text" maxlength="1000" value="{{ $submission->feedback }}" class="field py-2" placeholder="Well done…">
                            </div>
                            <x-button variant="secondary" size="sm" icon="check">Save mark</x-button>
                        </form>
                    </x-card>
                @empty
                    <x-card><x-empty-state icon="inbox" title="Nothing handed in yet" /></x-card>
                @endforelse
            </div>
        </x-tab>
        <x-tab name="missing">
            <x-card :padding="false">
                <ul class="m-0 list-none divide-y divide-line p-0">
                    @forelse ($students->reject(fn ($student) => $submissions->has($student->id)) as $student)
                        <li class="px-5 py-3 font-medium">{{ $student->registerName() }}</li>
                    @empty
                        <li class="px-5 py-4 text-muted">Everyone has handed it in.</li>
                    @endforelse
                </ul>
            </x-card>
        </x-tab>
        <x-tab name="brief">
            <x-card><div class="text-[15px] whitespace-pre-line">{{ $assignment->instructions }}</div></x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
