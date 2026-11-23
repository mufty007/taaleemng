@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'submit',
])

@php
    $classes = [
        'inline-flex items-center justify-center gap-2 font-semibold whitespace-nowrap transition disabled:cursor-not-allowed disabled:opacity-60',
        match ($size) {
            'sm' => 'rounded-lg px-3 py-2 text-sm',
            'lg' => 'rounded-xl px-5 py-3.5 text-base',
            default => 'rounded-xl px-4 py-2.5 text-[15px]',
        },
        match ($variant) {
            'brand' => 'bg-brand-600 text-white hover:bg-ink',
            'secondary' => 'border border-line bg-white text-ink hover:border-brand hover:text-brand-600',
            'soft' => 'bg-brand-50 text-brand-600 hover:bg-brand-100',
            'ghost' => 'text-muted hover:bg-chip hover:text-ink',
            'danger' => 'bg-danger text-white hover:bg-[#912018]',
            default => 'bg-ink text-white hover:bg-brand-600',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" class="size-4" aria-hidden="true" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" class="size-4" aria-hidden="true" />
        @endif
        {{ $slot }}
    </button>
@endif
