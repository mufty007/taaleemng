{{--
    A titled block of a report. Short panels stay whole on paper; pass :keep="false"
    for long tables that may run onto the next page.
--}}
@props(['title', 'description' => null, 'padding' => true, 'keep' => true])

<section {{ $attributes->class(['min-w-0 rounded-2xl border border-line bg-white', 'break-inside-avoid' => $keep]) }}>
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4 print:px-4 print:py-2.5">
        <div class="min-w-0">
            <h2 class="m-0 text-base font-semibold print:text-sm">{{ $title }}</h2>
            @if ($description)
                <p class="m-0 text-sm text-muted print:text-xs">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="no-print flex flex-wrap items-center gap-2 text-sm">{{ $actions }}</div>
        @endisset
    </header>
    <div @class(['p-5 print:p-4' => $padding])>
        {{ $slot }}
    </div>
</section>
