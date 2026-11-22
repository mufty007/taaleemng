<x-layouts.app :title="$announcement->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.announcements.index') }}" class="hover:text-ink">Notice board</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="truncate text-ink">{{ $announcement->title }}</span>
    </div>

    <article class="max-w-3xl">
        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge tone="brand">To {{ $announcement->audienceLabel() }}</x-badge>
                @if ($announcement->is_pinned)<x-badge tone="gold">Pinned</x-badge>@endif
                @if ($announcement->expires_at)<x-badge>Until {{ $announcement->expires_at->format('j M') }}</x-badge>@endif
            </div>
            <h1 class="m-0 mt-3 text-[26px] leading-tight font-bold tracking-[-0.02em]">{{ $announcement->title }}</h1>
            <p class="m-0 mt-1 text-sm text-muted">{{ $announcement->author?->name ?? 'The school' }} · {{ $announcement->published_at->format('l j F Y, g:ia') }}</p>
            <div class="mt-5 text-[16px] leading-relaxed whitespace-pre-line">{{ $announcement->body }}</div>

            @if ($readCount !== null || $canDelete)
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                    @if ($readCount !== null)<span class="text-sm text-muted">Opened by {{ $readCount }} {{ \Illuminate\Support\Str::plural('person', $readCount) }}</span>@endif
                    @if ($canDelete)
                        <form method="POST" action="{{ route('school.announcements.destroy', $announcement) }}" data-confirm="Take this notice down? Parents and staff will no longer see it." data-confirm-title="Take notice down?" data-confirm-action="Take down">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" size="sm" icon="trash-2">Take it down</x-button>
                        </form>
                    @endif
                </div>
            @endif
        </x-card>
    </article>
</x-layouts.app>
