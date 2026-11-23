@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand', 'href' => null])

@php
    $toneClasses = match ($tone) {
        'naija' => 'bg-naija-50 text-naija-700',
        'gold' => 'bg-gold-50 text-gold',
        'sky' => 'bg-sky-50 text-sky',
        'danger' => 'bg-danger-50 text-danger',
        default => 'bg-brand-50 text-brand-600',
    };
    $tag = $href ? 'a' : 'div';
@endphp

{{-- Two to a row on phones, so the number shrinks with the screen and the hint keeps to two lines. --}}
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['group flex min-w-0 flex-col gap-2 rounded-2xl border border-line bg-white p-4 sm:gap-3 sm:p-5', 'transition hover:border-brand hover:shadow-card' => $href]) }}>
    <div class="flex items-start justify-between gap-2">
        <span class="text-[13px] leading-snug font-medium text-muted sm:text-sm">{{ $label }}</span>
        @if ($icon)
            <span class="grid size-8 flex-none place-items-center rounded-xl sm:size-9 {{ $toneClasses }}">
                <x-dynamic-component :component="'lucide-'.$icon" class="size-4 sm:size-[18px]" aria-hidden="true" />
            </span>
        @endif
    </div>
    <div class="text-[clamp(1.25rem,5.4vw,1.875rem)] leading-tight font-bold tracking-[-0.02em] tabular-nums [overflow-wrap:anywhere]">{{ $value }}</div>
    @if ($hint)
        <div class="line-clamp-2 text-xs text-muted sm:text-[13px]">{{ $hint }}</div>
    @endif
</{{ $tag }}>
