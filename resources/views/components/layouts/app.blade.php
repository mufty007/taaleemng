@props(['title' => null])

@php
    $user = auth()->user();
    $inPlatform = request()->routeIs('platform.*');
    $viewingAs = \App\Support\Platform\ViewAs::active();

    $navigation = match (true) {
        $inPlatform => [[
            'label' => 'Platform',
            'items' => [
                ['label' => 'Overview', 'icon' => 'layout-dashboard', 'route' => 'platform.dashboard', 'active' => request()->routeIs('platform.dashboard')],
                ['label' => 'Schools', 'icon' => 'school', 'route' => 'platform.schools.index', 'active' => request()->routeIs('platform.schools.*')],
                ['label' => 'Billing', 'icon' => 'receipt', 'route' => 'platform.billing.index', 'active' => request()->routeIs('platform.billing.*')],
                ['label' => 'Broadcasts', 'icon' => 'radio', 'route' => 'platform.broadcasts.index', 'active' => request()->routeIs('platform.broadcasts.*')],
                ['label' => 'Users', 'icon' => 'users-round', 'route' => 'platform.users.index', 'active' => request()->routeIs('platform.users.*')],
                ['label' => 'Leads', 'icon' => 'inbox', 'route' => 'platform.leads.index', 'active' => request()->routeIs('platform.leads.*')],
                ['label' => 'Health', 'icon' => 'activity', 'route' => 'platform.health', 'active' => request()->routeIs('platform.health*')],
                ['label' => 'Audit log', 'icon' => 'scroll-text', 'route' => 'platform.audit', 'active' => request()->routeIs('platform.audit')],
                ['label' => 'Settings', 'icon' => 'settings', 'route' => 'platform.settings.edit', 'active' => request()->routeIs('platform.settings.*')],
            ],
        ]],
        $currentSchool !== null => \App\Support\Navigation\SchoolNavigation::for($user),
        default => [],
    };

    // Notices from Ta'aleem for this person in this school.
    $broadcasts = $currentSchool !== null && ! $inPlatform ? \App\Models\PlatformBroadcast::forViewer($user, $currentSchool) : collect();

    // Phones get a tab bar along the bottom for the pages this role opens most.
    $tabs = $currentSchool !== null && ! $inPlatform ? \App\Support\Navigation\SchoolNavigation::tabs($user) : [];

    // The bell: the latest notices meant for this person, and how many they haven't opened.
    $notices = $currentSchool !== null && ! $inPlatform
        ? \App\Models\Announcement::query()->visibleTo($user)->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])->noticeBoardOrder()->limit(6)->get()
        : collect();
    $unreadNotices = $currentSchool !== null && ! $inPlatform
        ? \App\Models\Announcement::query()->visibleTo($user)->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))->count()
        : 0;

    $membership = $currentSchool !== null && ! $inPlatform
        ? $user->schools()->where('schools.id', $currentSchool->id)->first()?->membership
        : null;
    $roleTour = $membership && blank($membership->onboarding_completed_at)
        ? \App\Support\Schools\RoleOnboarding::for($user, $currentSchool)
        : null;
@endphp

<x-layouts.base :title="$title" data-app-shell>
    <x-slot:head>
        <script>try { if (JSON.parse(localStorage.getItem('sidebar.rail'))) document.documentElement.classList.add('sidebar-rail'); } catch (e) {}</script>
        @if ($tabs !== [])
            {{-- Toasts and the page end sit above the phone tab bar. --}}
            <style>@media (max-width: 1023.98px) { :root { --tabbar-h: calc(4.25rem + env(safe-area-inset-bottom)); } }</style>
        @endif
    </x-slot:head>

    <div data-app-shell-root x-data="shell" @keydown.escape.window="sidebar = false" class="min-h-screen transition-[padding] duration-200 lg:pl-[272px] lg:rail:pl-[76px]">

        {{-- Mobile backdrop --}}
        <div x-show="sidebar" x-transition.opacity x-cloak @click="sidebar = false" class="fixed inset-0 z-40 bg-ink/40 lg:hidden" aria-hidden="true"></div>

        {{-- SIDEBAR --}}
        <aside
            id="sidebar"
            :class="sidebar && 'translate-x-0!'"
            class="fixed inset-y-0 left-0 z-50 flex w-[272px] -translate-x-full flex-col border-r border-line bg-white transition-[transform,width] duration-200 lg:translate-x-0 lg:rail:w-[76px]"
            aria-label="Sidebar"
        >
            <div class="flex items-center justify-between px-5 pt-5 pb-4 lg:rail:justify-center lg:rail:px-0">
                <a href="{{ route('app') }}" aria-label="{{ config('app.name') }} home">
                    <x-logo class="h-7 lg:rail:hidden" />
                    <x-logo :mark="true" class="hidden lg:rail:block" />
                </a>
                <button type="button" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip lg:hidden" @click="sidebar = false" aria-label="Close menu">
                    <x-lucide-x class="size-5" aria-hidden="true" />
                </button>
            </div>

            {{-- School switcher --}}
            <div class="px-3 pb-2">
                @if ($inPlatform)
                    <div class="flex items-center gap-3 rounded-xl bg-ink px-3 py-2.5 text-white lg:rail:justify-center lg:rail:px-0" title="Platform console">
                        <span class="grid size-9 flex-none place-items-center rounded-lg bg-white/10"><x-lucide-shield-check class="size-[18px]" aria-hidden="true" /></span>
                        <span class="min-w-0 lg:rail:hidden">
                            <span class="block truncate text-sm font-semibold">Platform console</span>
                            <span class="block text-xs text-ink-300">All schools</span>
                        </span>
                    </div>
                @elseif ($currentSchool)
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button
                            type="button"
                            @click="open = ! open"
                            :aria-expanded="open.toString()"
                            aria-haspopup="true"
                            title="{{ $currentSchool->name }}"
                            class="flex w-full items-center gap-3 rounded-xl border border-line px-3 py-2.5 text-left transition hover:border-brand lg:rail:justify-center lg:rail:border-transparent lg:rail:px-0"
                        >
                            @if ($currentSchool->logoUrl())
                                <img src="{{ $currentSchool->logoUrl() }}" alt="" class="size-9 flex-none rounded-lg object-contain">
                            @else
                                <span class="grid size-9 flex-none place-items-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600">{{ $currentSchool->initials() }}</span>
                            @endif
                            <span class="min-w-0 flex-1 lg:rail:hidden">
                                <span class="block truncate text-sm font-semibold">{{ $currentSchool->name }}</span>
                                <span class="block text-xs text-muted">{{ $currentSchool->status->label() }}{{ $currentSchool->state ? ' · '.$currentSchool->state : '' }}</span>
                            </span>
                            <x-lucide-chevrons-up-down class="size-4 flex-none text-muted lg:rail:hidden" aria-hidden="true" />
                        </button>
                        <div x-show="open" x-transition.origin.top x-cloak class="absolute top-full left-0 z-10 mt-1.5 w-full min-w-60 rounded-xl border border-line bg-white p-1.5 shadow-card">
                            @foreach ($switchableSchools as $school)
                                <a href="{{ route('school.dashboard', $school) }}" @class(['flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm hover:bg-chip', 'font-semibold text-ink' => $school->is($currentSchool), 'text-muted' => ! $school->is($currentSchool)])>
                                    <span class="grid size-7 flex-none place-items-center rounded-md bg-chip text-[11px] font-bold text-ink">{{ $school->initials() }}</span>
                                    <span class="truncate">{{ $school->name }}</span>
                                    @if ($school->is($currentSchool))
                                        <x-lucide-check class="ml-auto size-4 text-brand-600" aria-hidden="true" />
                                    @endif
                                </a>
                            @endforeach
                            @if ($switchableSchools->count() > 1)
                                <a href="{{ route('schools.pick') }}" class="mt-1 block border-t border-line px-2.5 pt-2.5 pb-1.5 text-sm font-medium text-brand-600 hover:text-ink">All my schools</a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <nav id="sidebar-nav" x-ref="nav" data-keep-scroll class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Main">
                @forelse ($navigation as $group)
                    @php
                        $groupKey = \Illuminate\Support\Str::slug($group['label']);
                        $groupHasActive = collect($group['items'])->contains('active', true);
                    @endphp
                    <div x-data="navGroup('{{ $groupKey }}', @js($groupHasActive))" class="mt-3 first:mt-1">
                        <button
                            type="button"
                            @click="toggle()"
                            :aria-expanded="open.toString()"
                            aria-controls="nav-{{ $groupKey }}"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-[11px] font-semibold tracking-[0.12em] text-muted uppercase hover:text-ink lg:rail:hidden"
                        >
                            {{ $group['label'] }}
                            <x-lucide-chevron-down class="size-3.5 transition-transform" ::class="open ? '' : '-rotate-90'" aria-hidden="true" />
                        </button>
                        <div class="mx-3 my-2 hidden h-px bg-line lg:rail:block" aria-hidden="true"></div>
                        <ul id="nav-{{ $groupKey }}" x-show="open || rail" x-collapse class="m-0 flex list-none flex-col gap-0.5 p-0">
                            @foreach ($group['items'] as $item)
                                <li>
                                    @if ($item['route'])
                                        <a
                                            href="{{ route($item['route']) }}"
                                            title="{{ $item['label'] }}"
                                            @if ($item['active']) aria-current="page" @endif
                                            @class([
                                                'flex items-center gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition lg:rail:justify-center lg:rail:px-0',
                                                'bg-brand-50 text-brand-600' => $item['active'],
                                                'text-ink hover:bg-chip' => ! $item['active'],
                                            ])
                                        >
                                            <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-[18px] flex-none" aria-hidden="true" />
                                            <span class="lg:rail:sr-only">{{ $item['label'] }}</span>
                                        </a>
                                    @else
                                        <span class="flex cursor-default items-center gap-3 rounded-xl px-3 py-2 text-[15px] font-medium text-placeholder lg:rail:justify-center lg:rail:px-0" title="{{ $item['label'] }} (coming soon)">
                                            <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-[18px] flex-none" aria-hidden="true" />
                                            <span class="lg:rail:sr-only">{{ $item['label'] }}</span>
                                            <span class="ml-auto rounded-full bg-chip px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted uppercase lg:rail:hidden">Soon</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="mt-1 flex flex-col gap-1">
                        <div class="px-3 pb-1.5 text-[11px] font-semibold tracking-[0.12em] text-muted uppercase lg:rail:hidden">Your schools</div>
                        @foreach ($switchableSchools as $school)
                            <a href="{{ route('school.dashboard', $school) }}" title="{{ $school->name }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-[15px] font-medium hover:bg-chip lg:rail:justify-center lg:rail:px-0">
                                <span class="grid size-7 flex-none place-items-center rounded-md bg-brand-50 text-[11px] font-bold text-brand-600">{{ $school->initials() }}</span>
                                <span class="truncate lg:rail:sr-only">{{ $school->name }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforelse

                @if ($user->is_platform_admin && ! $inPlatform)
                    <a href="{{ route('platform.dashboard') }}" title="Platform console" class="mt-6 flex items-center gap-3 rounded-xl px-3 py-2 text-[15px] font-medium text-muted hover:bg-chip hover:text-ink lg:rail:justify-center lg:rail:px-0">
                        <x-lucide-shield-check class="size-[18px] flex-none" aria-hidden="true" /> <span class="lg:rail:sr-only">Platform console</span>
                    </a>
                @endif
            </nav>

            <div class="hidden border-t border-line p-3 lg:block">
                <button type="button" @click="toggleRail()" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-muted hover:bg-chip hover:text-ink lg:rail:justify-center lg:rail:px-0" :aria-label="rail ? 'Expand sidebar' : 'Collapse sidebar'" :title="rail ? 'Expand sidebar' : 'Collapse sidebar'">
                    <x-lucide-panel-left-close class="size-[18px] lg:rail:hidden" aria-hidden="true" />
                    <x-lucide-panel-left-open class="hidden size-[18px] lg:rail:block" aria-hidden="true" />
                    <span class="lg:rail:hidden">Collapse sidebar</span>
                </button>
            </div>
        </aside>

        {{-- TOPBAR --}}
        <header class="sticky top-0 z-30 border-b border-line bg-canvas/85 backdrop-blur-md">
            @if ($viewingAs)
                {{-- A super admin seeing the school through someone else's eyes. --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-danger px-4 py-2 text-sm text-white sm:px-6" role="status">
                    <x-lucide-eye class="size-4 flex-none" aria-hidden="true" />
                    <span class="min-w-0 flex-1"><strong class="font-semibold">Viewing as {{ \App\Support\Platform\ViewAs::describe($user) }}</strong><span class="hidden sm:inline"> · their account settings and chats stay closed</span></span>
                    <form method="POST" action="{{ route('view-as.stop') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-white/15 px-3 py-1 text-xs font-semibold text-white hover:bg-white/25">Stop viewing</button>
                    </form>
                </div>
            @elseif ($currentSchool && ! $inPlatform && $user->is_platform_admin)
                {{-- Support mode: a super admin inside a school, with a school admin's reach. --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-gold-50 px-4 py-2 text-sm text-ink ring-1 ring-gold-200 sm:px-6" role="status">
                    <x-lucide-life-buoy class="size-4 flex-none text-gold" aria-hidden="true" />
                    <span class="min-w-0 flex-1"><strong class="font-semibold">Support mode</strong> · {{ $currentSchool->name }}<span class="hidden sm:inline"> · you can do anything a school admin can, and it is logged</span></span>
                    <a href="{{ route('platform.schools.show', $currentSchool) }}" class="rounded-lg bg-white px-3 py-1 text-xs font-semibold text-ink ring-1 ring-gold-200 hover:bg-gold-50">Back to console</a>
                </div>
            @endif
            <div class="flex items-center gap-3 px-4 py-3 sm:px-6">
                <button type="button" class="grid size-10 place-items-center rounded-xl text-ink hover:bg-chip lg:hidden" @click="sidebar = true" aria-controls="sidebar" :aria-expanded="sidebar.toString()" aria-label="Open menu">
                    <x-lucide-menu class="size-5" aria-hidden="true" />
                </button>

                @if ($currentSchool && ! $inPlatform)
                    <span class="hidden items-center gap-2 rounded-full border border-line bg-white px-3 py-1.5 text-[13px] font-medium text-muted sm:inline-flex">
                        <x-lucide-calendar-range class="size-4 text-brand-600" aria-hidden="true" />
                        @if ($currentTerm)
                            <span class="text-ink">{{ $currentTerm->academicSession->name }} · {{ $currentTerm->name }}</span>
                            @if ($week = $currentTerm->weekOf(now()))
                                <span>· Week {{ $week }}</span>
                            @elseif (now()->gt($currentTerm->ends_on))
                                <span>· Ended</span>
                            @endif
                        @else
                            No session set up yet
                        @endif
                    </span>
                @endif

                <div class="ml-auto flex items-center gap-1.5">
                    @if ($currentSchool && ! $inPlatform)
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="true" class="relative grid size-10 place-items-center rounded-xl text-muted hover:bg-chip hover:text-ink" aria-label="Notices{{ $unreadNotices ? ', '.$unreadNotices.' unread' : '' }}">
                                <x-lucide-bell class="size-5" aria-hidden="true" />
                                @if ($unreadNotices)
                                    <span class="absolute top-1.5 right-1.5 grid min-w-4 place-items-center rounded-full bg-danger px-1 text-[10px] leading-4 font-bold text-white">{{ $unreadNotices > 9 ? '9+' : $unreadNotices }}</span>
                                @endif
                            </button>
                            <div x-show="open" x-transition.origin.top.right x-cloak class="absolute right-0 z-20 mt-1.5 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-line bg-white shadow-card">
                                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                                    <span class="text-sm font-semibold">Notices</span>
                                    @if ($unreadNotices)
                                        <form method="POST" action="{{ route('school.announcements.read') }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-ink">Mark all as read</button>
                                        </form>
                                    @endif
                                </div>
                                <ul class="m-0 max-h-96 list-none overflow-y-auto p-1.5">
                                    @forelse ($notices as $notice)
                                        @php
                                            $unread = $notice->reads->isEmpty();
                                        @endphp
                                        <li>
                                            <a href="{{ route('school.announcements.show', $notice) }}" class="flex gap-3 rounded-lg px-2.5 py-2 hover:bg-chip">
                                                <span @class(['mt-1.5 size-2 flex-none rounded-full', 'bg-brand-600' => $unread, 'bg-transparent' => ! $unread]) aria-hidden="true"></span>
                                                <span class="min-w-0 flex-1">
                                                    <span @class(['block truncate text-sm', 'font-semibold' => $unread])>{{ $notice->title }}</span>
                                                    <span class="block truncate text-xs text-muted">{{ \Illuminate\Support\Str::limit($notice->body, 80) }}</span>
                                                    <span class="block text-[11px] text-muted">{{ $notice->published_at->diffForHumans() }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @empty
                                        <li class="px-2.5 py-4 text-center text-sm text-muted">No notices yet.</li>
                                    @endforelse
                                </ul>
                                <a href="{{ route('school.announcements.index') }}" class="block border-t border-line px-4 py-2.5 text-center text-sm font-semibold text-brand-600 hover:bg-chip">Open the notice board</a>
                            </div>
                        </div>
                    @endif

                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="true" class="flex items-center gap-2.5 rounded-xl py-1.5 pr-2 pl-1.5 hover:bg-chip">
                            <span class="grid size-8 place-items-center rounded-full bg-ink text-xs font-semibold text-white">{{ $user->initials() }}</span>
                            <span class="hidden max-w-[160px] truncate text-sm font-semibold sm:block">{{ $user->name }}</span>
                            <x-lucide-chevron-down class="hidden size-4 text-muted sm:block" aria-hidden="true" />
                        </button>
                        <div x-show="open" x-transition.origin.top.right x-cloak class="absolute right-0 z-10 mt-1.5 w-60 rounded-xl border border-line bg-white p-1.5 shadow-card">
                            <div class="border-b border-line px-2.5 pt-1.5 pb-2.5">
                                <div class="truncate text-sm font-semibold">{{ $user->name }}</div>
                                <div class="truncate text-xs text-muted">{{ $user->email ?? $user->phone }}</div>
                            </div>
                            <a href="{{ route('account') }}" class="mt-1 flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm hover:bg-chip">
                                <x-lucide-user-cog class="size-4 text-muted" aria-hidden="true" /> Account &amp; security
                            </a>
                            @if ($switchableSchools->count() > 1)
                                <a href="{{ route('schools.pick') }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm hover:bg-chip">
                                    <x-lucide-building-2 class="size-4 text-muted" aria-hidden="true" /> Switch school
                                </a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-danger hover:bg-danger-50">
                                    <x-lucide-log-out class="size-4" aria-hidden="true" /> Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main data-app-main class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 pt-6 pb-[calc(1.5rem+var(--tabbar-h,0px))] sm:px-6 sm:pt-8 sm:pb-[calc(2rem+var(--tabbar-h,0px))]">
            {{-- A student or staff member could not be added because the plan's seats are full. --}}
            @if ($errors->has('plan') && $currentSchool)
                <div class="flex flex-wrap items-start gap-3 rounded-2xl border border-danger/30 bg-danger-50 px-4 py-3" role="alert">
                    <x-lucide-users class="mt-0.5 size-5 flex-none text-danger" aria-hidden="true" />
                    <p class="m-0 min-w-0 flex-1 text-sm text-ink">{{ $errors->first('plan') }}</p>
                    @can('school.manage')
                        <x-button variant="brand" size="sm" :href="route('school.settings.edit').'#subscription'">See plans</x-button>
                    @else
                        <span class="text-sm text-muted">Ask your school admin to upgrade.</span>
                    @endcan
                </div>
            @endif

            {{-- Notices from Ta'aleem (platform broadcasts), until they end or are dismissed. --}}
            @foreach ($broadcasts as $broadcast)
                @php
                    [$box, $icon] = match ($broadcast->level) {
                        \App\Enums\BroadcastLevel::Critical => ['border-danger/30 bg-danger-50', 'text-danger'],
                        \App\Enums\BroadcastLevel::Warning => ['border-gold-200 bg-gold-50', 'text-gold'],
                        default => ['border-brand-200 bg-brand-50', 'text-brand-600'],
                    };
                @endphp
                <div x-data="{ open: true }" x-show="open" x-collapse class="flex items-start gap-3 rounded-2xl border px-4 py-3 {{ $box }}" role="status">
                    <x-lucide-radio class="mt-0.5 size-5 flex-none {{ $icon }}" aria-hidden="true" />
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="m-0 font-semibold">{{ $broadcast->title }} <span class="font-normal text-muted">· from Ta’aleem</span></p>
                        <p class="m-0 mt-0.5 whitespace-pre-line text-ink">{{ $broadcast->body }}</p>
                    </div>
                    <form method="POST" action="{{ route('school.broadcasts.dismiss', $broadcast) }}" x-on:submit.prevent="open = false; fetch($el.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } })">
                        @csrf
                        <button type="submit" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-white/60 hover:text-ink" aria-label="Dismiss “{{ $broadcast->title }}”"><x-lucide-x class="size-4" aria-hidden="true" /></button>
                    </form>
                </div>
            @endforeach

            {{ $slot }}
        </main>

        {{-- PHONE TAB BAR --}}
        @if ($tabs !== [])
            <nav data-app-tabbar class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md lg:hidden print:hidden" aria-label="Quick">
                <ul class="m-0 mx-auto flex max-w-lg list-none items-stretch justify-around p-0 px-1">
                    @foreach ($tabs as $tab)
                        <li class="min-w-0 flex-1">
                            <a
                                href="{{ route($tab['route']) }}"
                                @if ($tab['active']) aria-current="page" @endif
                                @class([
                                    'flex h-[4.25rem] flex-col items-center justify-center gap-1 px-1 text-[11px] font-semibold transition',
                                    'text-brand-600' => $tab['active'],
                                    'text-muted hover:text-ink' => ! $tab['active'],
                                ])
                            >
                                <span @class(['grid h-7 w-12 place-items-center rounded-full transition', 'bg-brand-50' => $tab['active']])>
                                    <x-dynamic-component :component="'lucide-'.$tab['icon']" class="size-5" aria-hidden="true" />
                                </span>
                                <span class="max-w-full truncate">{{ $tab['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                    <li class="min-w-0 flex-1">
                        <button type="button" @click="sidebar = true" aria-controls="sidebar" :aria-expanded="sidebar.toString()" class="flex h-[4.25rem] w-full flex-col items-center justify-center gap-1 px-1 text-[11px] font-semibold text-muted transition hover:text-ink">
                            <span class="grid h-7 w-12 place-items-center rounded-full">
                                <x-lucide-layout-grid class="size-5" aria-hidden="true" />
                            </span>
                            <span>Menu</span>
                        </button>
                    </li>
                </ul>
            </nav>
        @endif
    </div>

    @if ($roleTour)
        <div
            data-app-role-tour
            x-data="{ open: true }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[55] flex items-end justify-center bg-ink/40 p-0 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="role-tour-title"
        >
            <div class="relative w-full max-w-md rounded-t-3xl bg-white p-6 shadow-panel sm:rounded-3xl">
                <h2 id="role-tour-title" class="m-0 text-xl font-bold tracking-[-0.02em]">{{ $roleTour['title'] }}</h2>
                <ol class="m-0 mt-4 list-decimal space-y-2 pl-5 text-[15px] text-muted">
                    @foreach ($roleTour['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
                <form method="POST" action="{{ route('school.onboarding.complete') }}" class="mt-6" x-on:submit="open = false">
                    @csrf
                    <x-button variant="brand" class="w-full justify-center">Got it</x-button>
                </form>
            </div>
        </div>
    @endif

    <x-flash />
    <x-confirm-dialog />
</x-layouts.base>
