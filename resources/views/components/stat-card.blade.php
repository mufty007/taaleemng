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

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['flex flex-col gap-3 rounded-2xl border border-line bg-white p-5', 'transition hover:border-brand hover:shadow-card' => $href]) }}>
    <div class="flex items-center justify-between gap-3">
        <span class="text-sm font-medium text-muted">{{ $label }}</span>
        @if ($icon)
            <span class="grid size-9 place-items-center rounded-xl {{ $toneClasses }}">
                <x-dynamic-component :component="'lucide-'.$icon" class="size-[18px]" aria-hidden="true" />
            </span>
        @endif
    </div>
    <div class="text-3xl font-bold tracking-[-0.02em]">{{ $value }}</div>
    @if ($hint)
        <div class="text-[13px] text-muted">{{ $hint }}</div>
    @endif
</{{ $tag }}>
