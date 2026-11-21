@props(['title' => null])

@php
    $user = auth()->user();
    $inPlatform = request()->routeIs('platform.*');

    $navigation = match (true) {
        $inPlatform => [[
            'label' => 'Platform',
            'items' => [
                ['label' => 'Overview', 'icon' => 'layout-dashboard', 'route' => 'platform.dashboard', 'active' => request()->routeIs('platform.dashboard')],
                ['label' => 'Schools', 'icon' => 'school', 'route' => 'platform.schools.index', 'active' => request()->routeIs('platform.schools.*')],
                ['label' => 'Leads', 'icon' => 'inbox', 'route' => 'platform.leads.index', 'active' => request()->routeIs('platform.leads.*')],
            ],
        ]],
        $currentSchool !== null => \App\Support\Navigation\SchoolNavigation::for($user),
        default => [],
    };
@endphp

<x-layouts.base :title="$title" data-app-shell>
    <x-slot:head>
        <script>try { if (JSON.parse(localStorage.getItem('sidebar.rail'))) document.documentElement.classList.add('sidebar-rail'); } catch (e) {}</script>
    </x-slot:head>

    <div x-data="shell" @keydown.escape.window="sidebar = false" class="min-h-screen transition-[padding] duration-200 lg:pl-[272px] lg:rail:pl-[76px]">

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
                            <span class="grid size-9 flex-none place-items-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600">{{ $currentSchool->initials() }}</span>
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

            <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Main">
                @forelse ($navigation as $group)
                    @php($groupKey = \Illuminate\Support\Str::slug($group['label']))
                    <div x-data="navGroup('{{ $groupKey }}')" class="mt-3 first:mt-1">
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
                    <button type="button" class="relative grid size-10 place-items-center rounded-xl text-muted hover:bg-chip hover:text-ink" aria-label="Notifications">
                        <x-lucide-bell class="size-5" aria-hidden="true" />
                    </button>

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

        <main class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8">
            {{ $slot }}
        </main>
    </div>

    <x-flash />
</x-layouts.base>
