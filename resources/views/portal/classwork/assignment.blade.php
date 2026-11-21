<x-layouts.app :title="$assignment->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.portal.classwork') }}#todo" class="hover:text-ink">My work</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $assignment->title }}</span>
    </div>

    <x-page-header :kicker="$assignment->classSubject->subject->name.' · '.($assignment->classSubject->teacher?->fullName() ?? '')" :title="$assignment->title" :description="($assignment->isPastDue() ? 'Was due ' : 'Due ').$assignment->due_at->format('l j F, g:ia')" />

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <x-card>
            <h2 class="m-0 text-base font-semibold">Instructions</h2>
            <div class="mt-2 text-[15px] whitespace-pre-line">{{ $assignment->instructions }}</div>
        </x-card>

        <x-card>
            @if ($submission?->marked_at)
                <h2 class="m-0 text-base font-semibold">Marked</h2>
                @if ($submission->score !== null)
                    <div class="mt-2 text-3xl font-bold text-naija-700">{{ rtrim(rtrim(number_format($submission->score, 1), '0'), '.') }}{{ $assignment->max_score ? ' / '.$assignment->max_score : '' }}</div>
                @endif
                @if ($submission->feedback)<p class="m-0 mt-2 rounded-xl bg-canvas px-4 py-3 text-[15px]">“{{ $submission->feedback }}”</p>@endif
                <h3 class="m-0 mt-4 text-sm font-semibold text-muted">Your answer</h3>
                <div class="mt-1 text-[15px] whitespace-pre-line">{{ $submission->body }}</div>
            @else
                <form method="POST" action="{{ route('school.portal.assignments.submit', $assignment) }}" class="flex flex-col gap-3">
                    @csrf
                    <label for="body" class="text-base font-semibold">{{ $submission ? 'Your answer (you can still change it)' : 'Your answer' }}</label>
                    <textarea id="body" name="body" rows="12" class="field resize-y" required @error('body') aria-invalid="true" @enderror>{{ old('body', $submission?->body) }}</textarea>
                    @error('body')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                    <x-button variant="brand" icon="send" class="self-start">{{ $submission ? 'Hand in again' : 'Hand in' }}</x-button>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.app>
