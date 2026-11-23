@props(['title', 'description' => null, 'kicker' => null])

<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-4']) }} data-enter>
    <div class="flex min-w-0 flex-col gap-1.5">
        @if ($kicker)
            <div class="kicker">{{ $kicker }}</div>
        @endif
        <h1 class="m-0 text-[28px] leading-tight font-bold tracking-[-0.03em] text-balance sm:text-[32px]">{{ $title }}</h1>
        @if ($description)
            <p class="m-0 max-w-2xl text-[15px] text-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
