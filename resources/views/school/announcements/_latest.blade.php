{{-- Compact unread notices for dashboards and portals. Expects $latestNotices (unread only). --}}
@if ($latestNotices->isNotEmpty())
    <x-card :padding="false" class="xl:sticky xl:top-4">
        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
            <h2 class="m-0 flex items-center gap-2 text-sm font-semibold">
                <x-lucide-megaphone class="size-4 text-brand-600" aria-hidden="true" />
                Notices
                <span class="rounded-full bg-danger-50 px-1.5 text-xs font-bold text-danger">{{ $latestNotices->count() }}</span>
            </h2>
            <a href="{{ route('school.announcements.index') }}" class="text-xs font-semibold text-brand-600 hover:text-ink">All</a>
        </div>
        <ul class="m-0 list-none p-0">
            @foreach ($latestNotices as $notice)
                <li class="flex items-start gap-2 border-b border-line px-3 py-2.5 last:border-b-0" x-data="{ gone: false }" x-show="! gone" x-collapse>
                    <form
                        method="POST"
                        action="{{ route('school.announcements.mark-read', $notice) }}"
                        class="mt-0.5 flex-none"
                        x-on:submit.prevent="
                            gone = true;
                            fetch($el.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        "
                    >
                        @csrf
                        <button
                            type="submit"
                            class="grid size-7 place-items-center rounded-full border-2 border-danger text-danger transition hover:bg-danger hover:text-white"
                            title="Mark as read"
                            aria-label="Mark “{{ $notice->title }}” as read"
                        >
                            <x-lucide-check class="size-3.5" stroke-width="3" aria-hidden="true" />
                        </button>
                    </form>
                    <a href="{{ route('school.announcements.show', $notice) }}" class="min-w-0 flex-1 hover:text-brand-600">
                        <span class="flex items-center gap-1.5">
                            <span class="truncate text-sm font-semibold">{{ $notice->title }}</span>
                            @if ($notice->is_pinned)
                                <x-lucide-pin class="size-3 flex-none text-gold" aria-label="Pinned" />
                            @endif
                        </span>
                        <span class="mt-0.5 line-clamp-2 block text-xs text-muted">{{ $notice->body }}</span>
                        <span class="mt-1 block text-[11px] text-muted">{{ $notice->published_at->diffForHumans() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
