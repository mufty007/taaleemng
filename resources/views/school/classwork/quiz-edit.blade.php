@php
    use App\Enums\ClassworkStatus;
    $published = $quiz->status === ClassworkStatus::Published;
@endphp

<x-layouts.app :title="$quiz->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.classwork.index') }}" class="hover:text-ink">Classwork</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $quiz->title }}</span>
    </div>

    <div x-data="quizBuilder({ questions: @js($questions), url: @js(route('school.classwork.quizzes.questions', $quiz)), locked: @js($locked) })" class="flex flex-col gap-5">
        <x-page-header :kicker="$quiz->classSubject->classArm->displayName().' · '.$quiz->classSubject->subject->name.' · '.$quiz->kind->label()" :title="$quiz->title">
            <x-slot:description><span x-text="questions.length"></span> questions · <span x-text="totalMarks"></span> marks{{ $quiz->duration_minutes ? ' · '.$quiz->duration_minutes.' minutes' : '' }}</x-slot:description>
            <x-slot:actions>
                <x-badge :tone="$published ? 'naija' : 'gold'">{{ $published ? 'Published' : 'Draft' }}</x-badge>
                <x-button variant="secondary" icon="settings-2" type="button" x-on:click="$dispatch('open-drawer', 'quiz-settings')">Settings</x-button>
                <x-button variant="secondary" icon="bar-chart-3" :href="route('school.classwork.quizzes.results', $quiz)">Results</x-button>
                <form method="POST" action="{{ route('school.classwork.quizzes.publish', $quiz) }}">
                    @csrf
                    <x-button :variant="$published ? 'ghost' : 'brand'" :icon="$published ? 'eye-off' : 'send'">{{ $published ? 'Unpublish' : 'Publish to class' }}</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        @if ($locked)
            <div class="rounded-2xl border border-gold-200 bg-gold-50 px-5 py-3 text-[15px] text-gold">Students have started this, so the questions are locked.</div>
        @endif
        <div x-show="error" x-cloak class="rounded-2xl bg-danger-50 px-5 py-3 text-[15px] font-medium text-danger" role="alert" x-text="error"></div>

        <template x-for="(question, index) in questions" :key="index">
            <div class="rounded-2xl border border-line bg-white p-5" :data-question="index">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="grid size-8 place-items-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600" x-text="index + 1"></span>
                    <select class="field w-auto py-1.5 text-sm" x-model="question.type" x-on:change="changeType(question)" :disabled="locked" aria-label="Question type">
                        @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <label class="ml-auto flex items-center gap-2 text-sm">Marks <input type="number" min="1" max="20" class="field w-16 py-1.5" x-model.number="question.marks" :disabled="locked"></label>
                    <div class="flex gap-1" x-show="! locked">
                        <button type="button" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" x-on:click="move(index, -1)" aria-label="Move up"><x-lucide-arrow-up class="size-4" aria-hidden="true" /></button>
                        <button type="button" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" x-on:click="move(index, 1)" aria-label="Move down"><x-lucide-arrow-down class="size-4" aria-hidden="true" /></button>
                        <button type="button" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger" x-on:click="remove(index)" aria-label="Delete question"><x-lucide-trash-2 class="size-4" aria-hidden="true" /></button>
                    </div>
                </div>
                <textarea rows="2" class="field mt-3 resize-y" placeholder="Type the question…" x-model="question.prompt" :disabled="locked" aria-label="Question"></textarea>

                <template x-if="question.type === 'single' || question.type === 'multiple'">
                    <div class="mt-3 flex flex-col gap-2">
                        <p class="m-0 text-xs text-muted" x-text="question.type === 'multiple' ? 'Tick every correct option.' : 'Tick the correct option.'"></p>
                        <template x-for="(option, optionIndex) in question.options" :key="option.key">
                            <div class="flex items-center gap-2">
                                <button type="button" class="grid size-9 flex-none place-items-center rounded-lg border text-sm font-bold" :class="question.answer.includes(option.key) ? 'border-naija bg-naija text-white' : 'border-line text-muted hover:border-ink'" x-on:click="locked || toggleAnswer(question, option.key)" :aria-pressed="question.answer.includes(option.key)" x-text="option.key"></button>
                                <input type="text" class="field py-2" placeholder="Option" x-model="option.text" :disabled="locked" :aria-label="'Option ' + option.key">
                                <button type="button" class="grid size-8 flex-none place-items-center rounded-lg text-muted hover:text-danger" x-show="! locked && question.options.length > 2" x-on:click="removeOption(question, optionIndex)" aria-label="Remove option"><x-lucide-x class="size-4" aria-hidden="true" /></button>
                            </div>
                        </template>
                        <button type="button" class="self-start text-sm font-semibold text-brand-600 hover:text-ink" x-show="! locked && question.options.length < 8" x-on:click="addOption(question)">+ Add an option</button>
                    </div>
                </template>
                <template x-if="question.type === 'true_false'">
                    <div class="mt-3 flex gap-2">
                        <template x-for="value in ['true', 'false']" :key="value">
                            <button type="button" class="rounded-lg border px-4 py-2 text-sm font-semibold" :class="question.answer.includes(value) ? 'border-naija bg-naija text-white' : 'border-line text-muted'" x-on:click="locked || toggleAnswer(question, value)" x-text="value === 'true' ? 'True' : 'False'"></button>
                        </template>
                    </div>
                </template>
                <template x-if="question.type === 'short'">
                    <div class="mt-3">
                        <input type="text" class="field" placeholder="Accepted answer(s), separated by |  e.g. Abuja | FCT" :value="question.answer[0] ?? ''" x-on:input="question.answer = [$event.target.value]" :disabled="locked" aria-label="Accepted answers">
                        <p class="m-0 mt-1 text-xs text-muted">Marked correct if the student’s answer matches any of these, ignoring capitals.</p>
                    </div>
                </template>
            </div>
        </template>

        <div x-show="! questions.length" class="rounded-2xl border border-dashed border-line bg-white px-6 py-10 text-center text-muted">No questions yet. Add the first one below.</div>

        <div class="sticky bottom-[calc(1rem+var(--tabbar-h,0px))] z-10 flex flex-wrap items-center gap-2 rounded-2xl border border-line bg-white/95 p-3 shadow-card backdrop-blur" x-show="! locked">
            <x-button variant="secondary" size="sm" icon="list-checks" type="button" x-on:click="add('single')">Multiple choice</x-button>
            <x-button variant="secondary" size="sm" icon="circle-help" type="button" x-on:click="add('true_false')">True / false</x-button>
            <x-button variant="secondary" size="sm" icon="pencil" type="button" x-on:click="add('short')">Short answer</x-button>
            <span class="ml-auto text-sm text-muted" x-text="saving ? 'Saving…' : (savedAt ? 'Saved ' + savedAt : '')"></span>
            <x-button variant="brand" icon="check" type="button" x-on:click="save()" x-bind:disabled="saving">Save questions</x-button>
        </div>
    </div>

    <x-drawer name="quiz-settings" title="Settings" :show="$errors->settings->isNotEmpty()">
        <form method="POST" action="{{ route('school.classwork.quizzes.update', $quiz) }}" class="flex flex-col gap-4" id="quiz-settings-form">
            @csrf
            @method('PATCH')
            <x-field label="Title" name="title" :value="$quiz->title" bag="settings" required />
            <x-select-field label="Type" name="kind" :options="$kinds" :value="$quiz->kind->value" bag="settings" />
            <div class="flex flex-col gap-1.5">
                <label for="quiz-instructions" class="text-sm font-semibold">Instructions</label>
                <textarea id="quiz-instructions" name="instructions" rows="3" class="field resize-y" placeholder="e.g. Answer all questions. Calculators are not allowed.">{{ old('instructions', $quiz->instructions) }}</textarea>
            </div>
            <x-field label="Time limit (minutes)" name="duration_minutes" type="number" min="1" max="300" :value="$quiz->duration_minutes" bag="settings" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Opens" name="opens_at" type="datetime-local" :value="$quiz->opens_at?->format('Y-m-d\TH:i')" bag="settings" />
                <x-field label="Closes" name="closes_at" type="datetime-local" :value="$quiz->closes_at?->format('Y-m-d\TH:i')" bag="settings" />
            </div>
            @foreach (['shuffle_questions' => 'Shuffle the question order for each student', 'show_answers' => 'Show students the correct answers after it closes'] as $field => $label)
                <label class="flex items-center gap-3 text-[15px]">
                    <input type="hidden" name="{{ $field }}" value="0">
                    <input type="checkbox" name="{{ $field }}" value="1" class="size-4 accent-brand-600" @checked(old($field, $quiz->{$field}))> {{ $label }}
                </label>
            @endforeach
        </form>
        <x-slot:footer><x-button variant="brand" form="quiz-settings-form">Save settings</x-button></x-slot:footer>
    </x-drawer>
</x-layouts.app>
