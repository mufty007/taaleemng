<x-layouts.app :title="$assignment->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.portal.classwork') }}#todo" class="hover:text-ink">My work</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $assignment->title }}</span>
    </div>

    <x-page-header :kicker="$assignment->classSubject->subject->name.' · '.($assignment->classSubject->teacher?->fullName() ?? '')" :title="$assignment->title" :description="($assignment->isPastDue() ? 'Was due ' : 'Due ').$assignment->due_at->format('l j F, g:ia').($assignment->max_score ? ' · marked out of '.$assignment->max_score : '')" />

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <x-card>
            <h2 class="m-0 text-base font-semibold">Instructions</h2>
            <div class="mt-2 text-[15px] whitespace-pre-line">{{ $assignment->instructions }}</div>
            @if ($assignment->files)
                <h3 class="m-0 mt-5 mb-2 text-sm font-semibold text-muted">From your teacher</h3>
                <x-file-list :files="$assignment->files" :url="fn ($index) => route('school.classwork.assignments.file', [$assignment, $index])" />
            @endif
        </x-card>

        <x-card>
            @if ($submission?->marked_at)
                <h2 class="m-0 text-base font-semibold">Marked</h2>
                @if ($submission->score !== null)
                    <div class="mt-2 text-3xl font-bold text-naija-700">{{ rtrim(rtrim(number_format($submission->score, 1), '0'), '.') }}{{ $assignment->max_score ? ' / '.$assignment->max_score : '' }}</div>
                @endif
                @if ($submission->feedback)<p class="m-0 mt-2 rounded-xl bg-canvas px-4 py-3 text-[15px]">“{{ $submission->feedback }}”</p>@endif
                <h3 class="m-0 mt-4 text-sm font-semibold text-muted">What you handed in</h3>
                @if ($submission->body)<div class="mt-1 text-[15px] whitespace-pre-line">{{ $submission->body }}</div>@endif
                <x-file-list :files="$submission->files" :url="fn ($index) => route('school.classwork.submissions.file', [$submission, $index])" class="mt-3" />
            @else
                <form method="POST" action="{{ route('school.portal.assignments.submit', $assignment) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="m-0 text-base font-semibold">{{ $submission ? 'Your work (you can still change it)' : 'Hand in your work' }}</h2>
                        @if ($submission)<x-badge tone="sky">Handed in {{ $submission->submitted_at->format('j M, g:ia') }}</x-badge>@endif
                    </div>

                    <x-file-picker label="Upload your paper or photos of your work" />

                    @if ($submission?->files)
                        <div class="flex flex-col gap-2">
                            <span class="text-sm font-semibold text-muted">Already handed in</span>
                            <x-file-list :files="$submission->files" :url="fn ($index) => route('school.classwork.submissions.file', [$submission, $index])" :removable="true" />
                        </div>
                    @endif

                    <div class="flex flex-col gap-1.5">
                        <label for="body" class="text-sm font-semibold">Or type your answer <span class="font-normal text-muted">(optional if you upload)</span></label>
                        <textarea id="body" name="body" rows="8" class="field resize-y" @error('body') aria-invalid="true" @enderror>{{ old('body', $submission?->body) }}</textarea>
                        @error('body')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                    </div>
                    <x-button variant="brand" icon="send" class="self-start">{{ $submission ? 'Hand in again' : 'Hand in' }}</x-button>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.app>
