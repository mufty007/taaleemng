{{-- Compact unread notices for dashboards and portals. Expects $latestNotices (unread only). --}}
@if ($latestNotices->isNotEmpty())
    <x-card :padding="false" class="xl:sticky xl:top-4">
        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
            <h2 class="m-0 flex items-center gap-2 text-sm font-semibold">
                <x-lucide-megaphone class="size-4 text-brand-600" aria-hidden="true" />
                Notices
                <span class="rounded-full bg-brand-50 px-1.5 text-xs font-bold text-brand-600">{{ $latestNotices->count() }} new</span>
            </h2>
            <a href="{{ route('school.announcements.index') }}" class="text-xs font-semibold text-brand-600 hover:text-ink">All</a>
        </div>
        <ul class="m-0 list-none p-0">
            @foreach ($latestNotices as $notice)
                <li class="group/notice flex items-start gap-3 border-b border-line py-3 pr-2 pl-4 last:border-b-0" x-data="{ gone: false }" x-show="! gone" x-collapse>
                    <span class="mt-1.5 size-2 flex-none rounded-full bg-brand-600" aria-hidden="true"></span>
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
                    <form
                        method="POST"
                        action="{{ route('school.announcements.mark-read', $notice) }}"
                        class="flex-none"
                        x-on:submit.prevent="
                            gone = true;
                            fetch($el.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        "
                    >
                        @csrf
                        <button
                            type="submit"
                            class="grid size-8 place-items-center rounded-lg text-placeholder transition hover:bg-naija-50 hover:text-naija focus-visible:text-naija"
                            title="Mark as read"
                            aria-label="Mark “{{ $notice->title }}” as read"
                        >
                            <x-lucide-check-check class="size-4" aria-hidden="true" />
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
