@props(['title' => null, 'description' => null])

@php
    $onHome = request()->routeIs('home');
    $section = fn (string $id): string => $onHome ? "#{$id}" : route('home')."#{$id}";
    $links = [
        ['label' => 'Modules', 'href' => $section('modules')],
        ['label' => 'Features', 'href' => $section('spotlight')],
        ['label' => 'Who it’s for', 'href' => $section('roles')],
        ['label' => 'Getting started', 'href' => $section('how')],
        ['label' => 'Pricing', 'href' => $section('pricing')],
        ['label' => 'FAQ', 'href' => $section('faq')],
        ['label' => 'Contact', 'href' => route('contact'), 'active' => request()->routeIs('contact')],
    ];
@endphp

<x-layouts.base :title="$title" :description="$description ?? 'Enrolment, school fees, CBT exams, results, payroll and attendance in one system, built for Nigerian schools.'">
    <div class="min-h-screen overflow-x-clip">
        <header
            x-data="{ open: false }"
            @keydown.escape.window="open = false"
            class="sticky top-0 z-30 border-b border-line bg-canvas/85 backdrop-blur-md"
        >
            <div class="mx-auto flex max-w-[1200px] items-center gap-6 px-4 py-3.5 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center" aria-label="{{ config('app.name') }} home">
                    <x-logo />
                </a>

                <nav class="hidden flex-1 flex-wrap justify-center gap-x-6 gap-y-2 text-[15px] font-medium lg:flex" aria-label="Main">
                    @foreach ($links as $link)
                        <a href="{{ $link['href'] }}" @class(['transition hover:text-ink', ($link['active'] ?? false) ? 'text-ink' : 'text-muted'])>{{ $link['label'] }}</a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-2.5 lg:ml-0">
                    @auth
                        <a href="{{ route('app') }}" class="hidden px-3.5 py-2.5 text-[15px] font-medium text-ink sm:inline-block">Open app</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden px-3.5 py-2.5 text-[15px] font-medium text-ink sm:inline-block">Log in</a>
                    @endauth
                    <a href="{{ $section('cta') }}" class="rounded-xl bg-ink px-3.5 py-2.5 text-[15px] font-semibold whitespace-nowrap text-white transition hover:bg-brand-600 sm:px-4">Book a <span class="hidden sm:inline">free </span>demo</a>
                    <button
                        type="button"
                        class="grid size-10 place-items-center rounded-xl text-ink hover:bg-chip lg:hidden"
                        @click="open = ! open"
                        :aria-expanded="open.toString()"
                        aria-controls="mobile-nav"
                        aria-label="Menu"
                    >
                        <x-lucide-menu class="size-5" x-show="! open" aria-hidden="true" />
                        <x-lucide-x class="size-5" x-show="open" x-cloak aria-hidden="true" />
                    </button>
                </div>
            </div>

            <nav id="mobile-nav" x-show="open" x-collapse x-cloak class="border-t border-line lg:hidden" aria-label="Main">
                <div class="mx-auto flex max-w-[1200px] flex-col px-4 py-3 sm:px-6">
                    @foreach ($links as $link)
                        <a href="{{ $link['href'] }}" @click="open = false" class="rounded-lg px-2 py-2.5 text-base font-medium text-ink hover:bg-chip">{{ $link['label'] }}</a>
                    @endforeach
                    @auth
                        <a href="{{ route('app') }}" class="rounded-lg px-2 py-2.5 text-base font-medium text-brand-600 hover:bg-chip">Open app</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-2 py-2.5 text-base font-medium text-brand-600 hover:bg-chip">Log in</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="mx-auto flex max-w-[1200px] flex-col gap-10 px-4 pt-12 pb-10 sm:px-6">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                <div class="col-span-2 flex flex-col gap-3">
                    <x-logo class="h-[30px] self-start" />
                    <p class="m-0 max-w-[320px] text-[15px] leading-relaxed text-muted">School management for Nigerian nursery, primary, secondary and Islamiyya schools.</p>
                </div>
                <div class="flex flex-col gap-2.5 text-[15px]">
                    <span class="font-semibold">Product</span>
                    <a href="{{ $section('modules') }}" class="text-muted hover:text-ink">All modules</a>
                    <a href="{{ $section('spotlight') }}" class="text-muted hover:text-ink">Results processing</a>
                    <a href="{{ $section('spotlight') }}" class="text-muted hover:text-ink">Exams &amp; CBT</a>
                    <a href="{{ $section('spotlight') }}" class="text-muted hover:text-ink">School fees</a>
                    <a href="{{ $section('spotlight') }}" class="text-muted hover:text-ink">Staff &amp; payroll</a>
                </div>
                <div class="flex flex-col gap-2.5 text-[15px]">
                    <span class="font-semibold">Company</span>
                    <a href="{{ route('contact') }}" class="text-muted hover:text-ink">Contact</a>
                    <a href="{{ route('privacy') }}" class="text-muted hover:text-ink">Privacy</a>
                    <a href="{{ route('terms') }}" class="text-muted hover:text-ink">Terms</a>
                    <a href="{{ route('login') }}" class="text-muted hover:text-ink">Log in</a>
                </div>
            </div>
            <div class="flex flex-wrap justify-between gap-3 border-t border-line pt-6 text-sm text-muted">
                <span>© {{ now()->year }} Ta’aleem. All rights reserved.</span>
                <span>Made for Nigerian schools, with care.</span>
            </div>
        </footer>
    </div>
</x-layouts.base>
