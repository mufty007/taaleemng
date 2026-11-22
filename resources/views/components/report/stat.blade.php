{{-- A headline figure on a report. Compact enough to sit four across on a printed A4 page. --}}
@props(['label', 'value', 'hint' => null, 'tone' => 'brand'])

@php
    $dot = match ($tone) {
        'naija' => 'bg-naija',
        'gold' => 'bg-gold',
        'sky' => 'bg-sky',
        'danger' => 'bg-danger',
        'muted' => 'bg-ink-300',
        default => 'bg-brand',
    };
@endphp

<div {{ $attributes->class(['flex min-w-0 flex-col gap-1 rounded-2xl border border-line bg-white p-4 break-inside-avoid print:rounded-xl print:px-3 print:py-2.5']) }}>
    <div class="flex items-center gap-2 text-[13px] font-medium text-muted print:text-[11px]">
        <span class="size-2 flex-none rounded-full {{ $dot }}" aria-hidden="true"></span>
        <span class="truncate">{{ $label }}</span>
    </div>
    <div class="truncate text-2xl font-bold tracking-[-0.02em] tabular-nums print:text-lg">{{ $value }}</div>
    @if ($hint)
        <div class="text-[13px] text-muted print:text-[11px]">{{ $hint }}</div>
    @endif
</div>
