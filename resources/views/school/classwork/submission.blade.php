@use('App\Support\Files\StoredFiles')

<x-layouts.app :title="$submission->student->fullName().' · '.$assignment->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.classwork.index', ['class_subject' => $assignment->class_subject_id]) }}#assignments" class="hover:text-ink">Classwork</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <a href="{{ route('school.classwork.assignments.show', $assignment) }}" class="hover:text-ink">{{ $assignment->title }}</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $submission->student->fullName() }}</span>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <x-avatar :person="$submission->student" class="size-14 rounded-2xl text-lg" />
        <div class="min-w-0 flex-1">
            <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">{{ $submission->student->registerName() }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                <span>{{ $assignment->classSubject->classArm->displayName() }} · {{ $assignment->classSubject->subject->name }}</span>
                <span aria-hidden="true">·</span>
                <span>Handed in {{ $submission->submitted_at->format('D j M, g:ia') }}</span>
                @if ($submission->isLate($assignment))<x-badge tone="gold">Late</x-badge>@endif
                @if ($submission->marked_at)<x-badge tone="naija">Marked</x-badge>@endif
            </div>
        </div>
        <nav class="flex items-center gap-2" aria-label="Other students’ work">
            <span class="text-sm text-muted">{{ $position }} of {{ $count }}</span>
            @if ($previous)
                <a href="{{ route('school.classwork.submissions.show', $previous) }}" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-muted hover:text-ink" aria-label="Previous: {{ $previous->student->fullName() }}"><x-lucide-chevron-left class="size-5" aria-hidden="true" /></a>
            @endif
            @if ($next)
                <a href="{{ route('school.classwork.submissions.show', $next) }}" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-muted hover:text-ink" aria-label="Next: {{ $next->student->fullName() }}"><x-lucide-chevron-right class="size-5" aria-hidden="true" /></a>
            @endif
        </nav>
    </div>

    <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- The work itself: photos and PDFs open right here. --}}
        <div class="flex min-w-0 flex-col gap-4">
            @if ($submission->body)
                <x-card>
                    <h2 class="m-0 text-sm font-semibold text-muted">Typed answer</h2>
                    <div class="mt-2 text-[16px] leading-relaxed whitespace-pre-line">{{ $submission->body }}</div>
                </x-card>
            @endif

            @foreach ($submission->files ?? [] as $index => $file)
                @php($url = route('school.classwork.submissions.file', [$submission, $index]))
                <x-card :padding="false" class="overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-line px-5 py-3">
                        <x-dynamic-component :component="'lucide-'.StoredFiles::icon($file)" class="size-5 text-brand-600" aria-hidden="true" />
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $file['name'] }}</span>
                        <span class="text-xs text-muted">{{ StoredFiles::size($file) }}</span>
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Open {{ $file['name'] }} in a new tab"><x-lucide-external-link class="size-4" aria-hidden="true" /></a>
                        <a href="{{ $url }}?download=1" data-native class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Download {{ $file['name'] }}"><x-lucide-download class="size-4" aria-hidden="true" /></a>
                    </div>
                    @if (StoredFiles::isImage($file))
                        <div x-data="{ turn: 0 }" class="relative bg-canvas">
                            <button type="button" x-on:click="turn = (turn + 90) % 360" class="absolute top-3 right-3 z-10 grid size-9 place-items-center rounded-lg bg-white/90 text-muted shadow-card hover:text-ink" aria-label="Turn the photo"><x-lucide-rotate-cw class="size-4" aria-hidden="true" /></button>
                            <img src="{{ $url }}" alt="Page {{ $index + 1 }} of {{ $submission->student->first_name }}’s work" class="mx-auto block max-h-[80vh] w-auto max-w-full object-contain transition-transform" :style="`transform: rotate(${turn}deg)`">
                        </div>
                    @elseif (StoredFiles::isPdf($file))
                        <iframe src="{{ $url }}#view=FitH" title="{{ $file['name'] }}" class="block h-[80vh] w-full border-0 bg-canvas"></iframe>
                    @else
                        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
                            <p class="m-0 text-[15px] text-muted">This file can’t be shown in the browser. Download it to read it.</p>
                            <x-button data-native variant="secondary" icon="download" :href="$url.'?download=1'">Download {{ $file['name'] }}</x-button>
                        </div>
                    @endif
                </x-card>
            @endforeach

            @if (! $submission->body && ! $submission->files)
                <x-card><x-empty-state icon="inbox" title="Nothing in this hand-in" /></x-card>
            @endif
        </div>

        {{-- Marking --}}
        <x-card class="xl:sticky xl:top-24">
            <h2 class="m-0 text-lg font-semibold">Mark</h2>
            <form method="POST" action="{{ route('school.classwork.submissions.mark', $submission) }}" class="mt-4 flex flex-col gap-4" x-data="{ feedback: @js(old('feedback', $submission->feedback ?? '')) }">
                @csrf
                @method('PATCH')
                <div class="flex flex-col gap-1.5">
                    <label for="score" class="text-sm font-semibold">Score{{ $assignment->max_score ? ' out of '.$assignment->max_score : '' }}</label>
                    <input id="score" name="score" type="number" step="0.5" min="0" max="{{ $assignment->max_score ?? 100 }}" value="{{ old('score', $submission->score) }}" class="field text-2xl font-bold" autofocus>
                    @error('score', 'mark'.$submission->id)<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="feedback" class="text-sm font-semibold">Feedback for the student</label>
                    <textarea id="feedback" name="feedback" rows="4" maxlength="1000" class="field resize-y" x-model="feedback" placeholder="What was good, and what to work on"></textarea>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['Excellent work. Well done!', 'Good effort. Keep it up.', 'Neat and well presented.', 'Show your working next time.', 'Incomplete. Please see me.', 'Handed in late. Be on time next time.'] as $quick)
                            <button type="button" class="rounded-full border border-line bg-white px-2.5 py-1 text-xs text-muted hover:border-brand-600 hover:text-brand-700" x-on:click="feedback = feedback ? feedback + ' ' + @js($quick) : @js($quick)">{{ $quick }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($unmarked > ($submission->marked_at ? 0 : 1))
                        <x-button variant="brand" icon="arrow-right" name="next" value="1">Save and mark next</x-button>
                    @endif
                    <x-button :variant="$unmarked > ($submission->marked_at ? 0 : 1) ? 'secondary' : 'brand'" icon="check">Save mark</x-button>
                </div>
                @if ($submission->marked_at)
                    <p class="m-0 text-sm text-muted">Marked {{ $submission->marked_at->format('j M, g:ia') }}{{ $submission->marker ? ' by '.$submission->marker->name : '' }}. Saving again updates the mark.</p>
                @endif
            </form>
            <div class="mt-5 border-t border-line pt-4 text-sm text-muted">{{ $unmarked }} {{ \Illuminate\Support\Str::plural('hand-in', $unmarked) }} still to mark in this assignment.</div>
        </x-card>
    </div>
</x-layouts.app>
