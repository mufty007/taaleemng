<x-layouts.app title="Classwork & CBT">
    <x-page-header kicker="Teaching" title="Classwork & CBT" description="Assignments, quizzes, class tests and computer-based exams for the subjects you teach. Quizzes mark themselves.">
        <x-slot:actions>
            @if ($subjectOptions)
                <x-button variant="secondary" icon="notebook-pen" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-assignment')">New assignment</x-button>
                <x-button icon="monitor-check" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-quiz')">New quiz or exam</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (! $subjectOptions)
        <x-card><x-empty-state icon="book-open" title="You don’t teach any subjects yet" body="Once the school assigns you subjects, you can set classwork for those classes here." /></x-card>
    @else
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <label for="class_subject" class="text-sm font-semibold">Class</label>
            <select id="class_subject" name="class_subject" class="field w-72 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                <option value="">All my classes</option>
                @foreach ($subjectOptions as $id => $label)<option value="{{ $id }}" @selected($selected === $id)>{{ $label }}</option>@endforeach
            </select>
        </form>

        <x-tabs :tabs="['quizzes' => ['label' => 'Quizzes & exams', 'count' => $quizzes->count()], 'assignments' => ['label' => 'Assignments', 'count' => $assignments->count()]]" label="Classwork">
            <x-tab name="quizzes">
                <x-card :padding="false">
                    @forelse ($quizzes as $quiz)
                        <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('school.classwork.quizzes.edit', $quiz) }}" class="font-semibold hover:text-brand-600">{{ $quiz->title }}</a>
                                <div class="text-sm text-muted">{{ $quiz->classSubject->classArm->displayName() }} · {{ $quiz->classSubject->subject->name }} · {{ $quiz->kind->label() }} · {{ $quiz->questions_count }} questions{{ $quiz->duration_minutes ? ' · '.$quiz->duration_minutes.' min' : '' }}</div>
                            </div>
                            <x-badge :tone="$quiz->status === \App\Enums\ClassworkStatus::Published ? ($quiz->isOpen() ? 'naija' : 'neutral') : 'gold'">{{ $quiz->status === \App\Enums\ClassworkStatus::Published ? ($quiz->isOpen() ? 'Open' : ($quiz->hasClosed() ? 'Closed' : 'Scheduled')) : 'Draft' }}</x-badge>
                            <span class="text-sm text-muted">{{ $quiz->attempts_count }} done</span>
                            <x-button variant="ghost" size="sm" icon="bar-chart-3" :href="route('school.classwork.quizzes.results', $quiz)">Results</x-button>
                        </div>
                    @empty
                        <x-empty-state icon="monitor-check" title="No quizzes yet" body="Set a quick quiz, a class test or a full CBT exam. Students take it on their phone or a computer, and it marks itself." />
                    @endforelse
                </x-card>
            </x-tab>
            <x-tab name="assignments">
                <x-card :padding="false">
                    @forelse ($assignments as $assignment)
                        <a href="{{ route('school.classwork.assignments.show', $assignment) }}" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3.5 last:border-b-0 hover:bg-canvas">
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">{{ $assignment->title }}</div>
                                <div class="text-sm text-muted">{{ $assignment->classSubject->classArm->displayName() }} · {{ $assignment->classSubject->subject->name }} · due {{ $assignment->due_at->format('D j M, g:ia') }}</div>
                            </div>
                            @if ($assignment->status === \App\Enums\ClassworkStatus::Draft)<x-badge tone="gold">Draft</x-badge>@endif
                            <span class="text-sm text-muted">{{ $assignment->submissions_count }} handed in</span>
                        </a>
                    @empty
                        <x-empty-state icon="notebook-pen" title="No assignments yet" body="Set homework with a due date. Students hand it in online and you mark it here." />
                    @endforelse
                </x-card>
            </x-tab>
        </x-tabs>

        <x-drawer name="new-quiz" title="New quiz or exam" description="Create it, add the questions, then publish it to the class." :show="$errors->quiz->isNotEmpty() || request('new') === 'quiz'">
            <form method="POST" action="{{ route('school.classwork.quizzes.store') }}" class="flex flex-col gap-4" id="new-quiz-form">
                @csrf
                <x-select-field label="Class and subject" name="class_subject_id" :options="$subjectOptions" :value="$selected" placeholder="Choose" bag="quiz" required />
                <x-field label="Title" name="title" bag="quiz" placeholder="e.g. Fractions quiz, First term CBT exam" required />
                <x-select-field label="Type" name="kind" :options="$kinds" value="quiz" bag="quiz" required />
                <x-field label="Time limit (minutes)" name="duration_minutes" type="number" min="1" max="300" bag="quiz" hint="Leave blank for no time limit." />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Opens" name="opens_at" type="datetime-local" bag="quiz" />
                    <x-field label="Closes" name="closes_at" type="datetime-local" bag="quiz" />
                </div>
            </form>
            <x-slot:footer><x-button variant="brand" icon="arrow-right" form="new-quiz-form">Create and add questions</x-button></x-slot:footer>
        </x-drawer>

        <x-drawer name="new-assignment" title="New assignment" description="Students see it in their portal and hand it in online." :show="$errors->assignment->isNotEmpty() || request('new') === 'assignment'" width="sm:max-w-xl">
            <form method="POST" action="{{ route('school.classwork.assignments.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4" id="new-assignment-form">
                @csrf
                <input type="hidden" name="publish" value="1">
                <x-select-field label="Class and subject" name="class_subject_id" :options="$subjectOptions" :value="$selected" placeholder="Choose" bag="assignment" required />
                <x-field label="Title" name="title" bag="assignment" placeholder="e.g. Essay: My favourite festival" required />
                <div class="flex flex-col gap-1.5">
                    <label for="instructions" class="text-sm font-semibold">Instructions</label>
                    <textarea id="instructions" name="instructions" rows="6" class="field resize-y" required>{{ old('instructions') }}</textarea>
                    @error('instructions', 'assignment')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Due" name="due_at" type="datetime-local" bag="assignment" :value="now()->addWeek()->setTime(8, 0)->format('Y-m-d\TH:i')" required />
                    <x-field label="Marked out of" name="max_score" type="number" min="1" max="100" bag="assignment" value="10" />
                </div>
                <x-file-picker bag="assignment" label="Attach a worksheet or notes (optional)" hint="PDF, Word, PowerPoint or pictures · up to 5 files" />
            </form>
            <x-slot:footer><x-button variant="brand" icon="send" form="new-assignment-form">Set assignment</x-button></x-slot:footer>
        </x-drawer>
    @endif
</x-layouts.app>
