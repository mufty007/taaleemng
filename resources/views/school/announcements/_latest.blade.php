{{-- The newest notices for a dashboard or the portal. Expects $latestNotices. --}}
@if ($latestNotices->isNotEmpty())
    <x-card :padding="false">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 class="m-0 flex items-center gap-2 text-base font-semibold"><x-lucide-megaphone class="size-[18px] text-brand-600" aria-hidden="true" /> Notice board</h2>
            <a href="{{ route('school.announcements.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">All notices</a>
        </div>
        @foreach ($latestNotices as $notice)
            @php($unread = $notice->reads->isEmpty())
            <a href="{{ route('school.announcements.show', $notice) }}" class="flex gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                <span @class(['mt-2 size-2 flex-none rounded-full', 'bg-brand-600' => $unread, 'bg-line' => ! $unread]) aria-hidden="true"></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span @class(['truncate', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $notice->title }}</span>
                        @if ($notice->is_pinned)<x-lucide-pin class="size-3.5 flex-none text-gold" aria-label="Pinned" />@endif
                    </span>
                    <span class="block truncate text-sm text-muted">{{ $notice->body }}</span>
                    <span class="block text-xs text-muted">{{ $notice->published_at->diffForHumans() }}</span>
                </span>
            </a>
        @endforeach
    </x-card>
@endif
