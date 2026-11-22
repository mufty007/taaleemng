@php
    $questions = $quiz->shuffle_questions && $attempt ? $quiz->questions->shuffle($attempt->id) : $quiz->questions;
    $taking = $attempt && $attempt->submitted_at === null;
@endphp

<x-layouts.app :title="$quiz->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.portal.classwork') }}" class="hover:text-ink">My work</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $quiz->title }}</span>
    </div>

    <x-page-header :kicker="$quiz->classSubject->subject->name.' · '.$quiz->kind->label()" :title="$quiz->title" :description="$quiz->questions->count().' questions · '.$quiz->questions->sum('marks').' marks'.($quiz->duration_minutes ? ' · '.$quiz->duration_minutes.' minutes' : '')" />

    @if (! $attempt)
        <x-card class="max-w-2xl">
            @if ($quiz->instructions)<p class="m-0 text-[15px] whitespace-pre-line">{{ $quiz->instructions }}</p>@endif
            <ul class="m-0 mt-3 flex flex-col gap-1 pl-5 text-[15px] text-muted">
                <li>You can only take it once.</li>
                @if ($quiz->duration_minutes)<li>You have {{ $quiz->duration_minutes }} minutes once you start. It hands itself in when time is up.</li>@endif
                <li>Your answers save as you go, so a dropped connection won’t lose them.</li>
            </ul>
            @if ($quiz->isOpen())
                <form method="POST" action="{{ route('school.portal.quizzes.start', $quiz) }}" class="mt-5">
                    @csrf
                    <x-button variant="brand" size="lg" icon="play">Start</x-button>
                </form>
            @else
                <p class="m-0 mt-5 font-semibold">{{ $quiz->hasClosed() ? 'This has closed.' : 'It opens '.$quiz->opens_at?->format('l j F, g:ia').'.' }}</p>
            @endif
        </x-card>
    @elseif ($taking)
        <form method="POST" action="{{ route('school.portal.quizzes.submit', $quiz) }}" x-ref="form" class="flex flex-col gap-4"
            x-data="quizTaker({ answers: @js((object) ($attempt->answers ?? [])), url: @js(route('school.portal.quizzes.answer', $quiz)), deadline: @js($deadline?->toIso8601String()) })"
            data-confirm="You can’t change your answers afterwards."
            data-confirm-title="Hand in now?"
            data-confirm-action="Hand in"
            data-confirm-tone="brand">
            @csrf
            <div class="sticky top-[72px] z-10 flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-white/95 px-5 py-3 shadow-card backdrop-blur">
                <span class="text-sm text-muted"><span x-text="answered"></span> of {{ $questions->count() }} answered</span>
                <span class="text-xs text-muted" x-text="status"></span>
                <span x-show="remaining !== null" class="ml-auto flex items-center gap-1.5 font-mono text-lg font-bold" :class="remaining < 120 ? 'text-danger' : ''"><x-lucide-timer class="size-5" aria-hidden="true" /><span x-text="clock"></span></span>
            </div>

            @foreach ($questions as $index => $question)
                <fieldset class="m-0 rounded-2xl border border-line bg-white p-5">
                    <legend class="sr-only">Question {{ $index + 1 }}</legend>
                    <div class="flex gap-3">
                        <span class="grid size-8 flex-none place-items-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600">{{ $index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="m-0 text-[16px] font-medium whitespace-pre-line">{{ $question->prompt }}</p>
                            <span class="text-xs text-muted">{{ $question->marks }} {{ str('mark')->plural($question->marks) }}</span>
                            <div class="mt-3 flex flex-col gap-2">
                                @if ($question->type === \App\Enums\QuestionType::ShortAnswer)
                                    <input type="text" name="answers[{{ $question->id }}]" class="field" placeholder="Your answer" x-model="answers[{{ $question->id }}]" x-on:input="changed()" autocomplete="off">
                                @else
                                    @foreach ($question->options ?? [] as $option)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 text-[15px] has-checked:border-brand-600 has-checked:bg-brand-50">
                                            @if ($question->type === \App\Enums\QuestionType::MultipleChoice)
                                                <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $option['key'] }}" class="size-4 accent-brand-600" x-model="answers[{{ $question->id }}]" x-init="answers[{{ $question->id }}] ??= []" x-on:change="changed()">
                                            @else
                                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option['key'] }}" class="size-4 accent-brand-600" x-model="answers[{{ $question->id }}]" x-on:change="changed()">
                                            @endif
                                            <span class="font-semibold text-muted">{{ $question->type === \App\Enums\QuestionType::TrueFalse ? '' : $option['key'].'.' }}</span> {{ $option['text'] }}
                                        </label>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </fieldset>
            @endforeach

            <x-button variant="brand" size="lg" icon="send" class="self-start">Hand in</x-button>
        </form>
    @else
        <x-card class="max-w-2xl">
            <div class="text-sm text-muted">Your score</div>
            <div class="text-4xl font-bold text-brand-600">{{ rtrim(rtrim(number_format($attempt->score, 1), '0'), '.') }} / {{ $attempt->max_score }} <span class="text-2xl text-muted">({{ $attempt->percent() }}%)</span></div>
            <p class="m-0 mt-2 text-sm text-muted">Handed in {{ $attempt->submitted_at->format('l j F, g:ia') }}.</p>
        </x-card>
        @if ($showAnswers)
            @foreach ($quiz->questions as $index => $question)
                @php($response = ($attempt->answers ?? [])[$question->id] ?? null)
                @php($correct = $question->type->isCorrect($question->answer, $response))
                <div @class(['rounded-2xl border bg-white p-5', 'border-naija/40' => $correct, 'border-danger/40' => ! $correct])>
                    <p class="m-0 font-medium">{{ $index + 1 }}. {{ $question->prompt }}</p>
                    <p class="m-0 mt-2 text-sm"><span class="text-muted">Your answer:</span> {{ is_array($response) ? implode(', ', $response) : ($response ?? '—') }} @if ($correct)<span class="font-semibold text-naija-700">✓</span>@else<span class="font-semibold text-danger">✗</span>@endif</p>
                    @unless ($correct)<p class="m-0 text-sm"><span class="text-muted">Correct:</span> {{ implode(' / ', $question->answer) }}</p>@endunless
                </div>
            @endforeach
        @elseif ($quiz->show_answers)
            <p class="m-0 text-sm text-muted">The correct answers will show here after it closes{{ $quiz->closes_at ? ' on '.$quiz->closes_at->format('j M, g:ia') : '' }}.</p>
        @endif
    @endif
</x-layouts.app>
