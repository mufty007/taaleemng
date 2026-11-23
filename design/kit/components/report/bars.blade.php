{{--
    A labelled horizontal bar per row, longest bar = largest value.

    <x-report.bars :rows="['Islam' => 120, 'Christianity' => 98]" tone="naija" />
    <x-report.bars :rows="$byMethod" money :share="false" />
--}}
@props(['rows', 'tone' => 'brand', 'money' => false, 'share' => true, 'empty' => 'Nothing recorded yet.'])

@use('App\Support\Money')

@php
    $bar = match ($tone) {
        'naija' => 'bg-naija',
        'gold' => 'bg-gold/70',
        'sky' => 'bg-sky',
        'danger' => 'bg-danger/70',
        default => 'bg-brand',
    };
    $values = array_map('intval', array_values($rows));
    $max = max(1, ...($values ?: [0]));
    $sum = array_sum($values);
@endphp

<ul {{ $attributes->class(['m-0 flex list-none flex-col gap-2 p-0 text-sm']) }}>
    @forelse ($rows as $label => $value)
        @php
            $display = $money ? Money::format((int) $value) : number_format((int) $value);
            $width = $value > 0 ? max(2, (int) round($value / $max * 100)) : 0;
        @endphp
        <li class="grid grid-cols-[minmax(0,9.5rem)_minmax(0,1fr)_auto] items-center gap-3" title="{{ $label }}: {{ $display }}">
            <span class="truncate">{{ $label }}</span>
            <span class="h-2 overflow-hidden rounded-full bg-chip" aria-hidden="true"><span class="block h-full rounded-full {{ $bar }}" style="width: {{ $width }}%"></span></span>
            <span class="text-right font-mono text-xs tabular-nums">
                {{ $display }}@if ($share && $sum > 0)<span class="text-muted"> · {{ round($value / $sum * 100) }}%</span>@endif
            </span>
        </li>
    @empty
        <li class="text-muted">{{ $empty }}</li>
    @endforelse
</ul>
