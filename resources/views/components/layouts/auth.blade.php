@props(['title', 'heading', 'subheading' => null])

<x-layouts.base :title="$title">
    <div class="grid min-h-screen grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="flex flex-col px-4 py-6 sm:px-10">
            <a href="{{ route('home') }}" class="self-start" aria-label="{{ config('app.name') }} home">
                <x-logo />
            </a>

            <main class="flex flex-1 items-center justify-center py-10">
                <div class="flex w-full max-w-[420px] flex-col gap-7" data-enter>
                    <div class="flex flex-col gap-2">
                        <h1 class="m-0 text-[34px] leading-tight font-bold tracking-[-0.035em]">{{ $heading }}</h1>
                        @if ($subheading)
                            <p class="m-0 text-base text-muted">{{ $subheading }}</p>
                        @endif
                    </div>

                    @if (session('status'))
                        <div class="rounded-xl bg-naija-50 px-4 py-3 text-[15px] font-medium text-naija-700" role="status">{{ session('status') }}</div>
                    @endif

                    {{ $slot }}
                </div>
            </main>

            <p class="m-0 text-center text-sm text-muted lg:text-left">© {{ now()->year }} Ta’aleem · <a href="{{ route('privacy') }}" class="hover:text-ink">Privacy</a></p>
        </div>

        <aside class="relative hidden overflow-hidden bg-ink p-12 text-white lg:flex lg:flex-col lg:justify-between" aria-hidden="true">
            <div class="absolute -top-40 -right-40 size-[480px] rounded-full border-[70px] border-white/5"></div>
            <div class="absolute -bottom-32 -left-24 size-[340px] rounded-full bg-brand-600/30"></div>

            <div class="relative kicker text-brand-200">Ta’aleem for Nigerian schools</div>

            <div class="relative flex flex-col gap-8">
                <p class="m-0 max-w-[480px] text-[40px] leading-[1.08] font-bold tracking-[-0.035em] text-balance">
                    From admission to <span class="inline-block -rotate-2 rounded-2xl bg-brand-600 px-3.5 pb-1">report card.</span>
                </p>
                <div class="flex max-w-[420px] flex-col gap-3">
                    <div class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-ink shadow-float">
                        <span class="grid size-9 place-items-center rounded-[10px] bg-naija-50 font-bold text-naija">₦</span>
                        <div>
                            <div class="text-sm font-semibold">First Term fees received</div>
                            <div class="text-xs text-muted">Chinedu’s parent · ₦185,000 · transfer</div>
                        </div>
                    </div>
                    <div class="ml-10 rounded-2xl rounded-br-[4px] bg-ink-800 px-4 py-3 ring-1 ring-ink-600">
                        <div class="mb-1 text-[11px] text-ink-300">Results · JSS 2A</div>
                        <div class="text-sm leading-snug">All 11 subjects approved. Report cards are ready to print.</div>
                    </div>
                </div>
            </div>

            <ul class="relative m-0 flex list-none flex-wrap gap-2 p-0 text-[13px] text-ink-200">
                @foreach (['3-term sessions', 'CA + exam scoring', 'Fees in Naira', 'Islamiyya subjects'] as $fit)
                    <li class="rounded-full bg-ink-700 px-3 py-1.5">{{ $fit }}</li>
                @endforeach
            </ul>
        </aside>
    </div>
</x-layouts.base>
