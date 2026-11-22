{{--
    A column chart for one series over time (weeks, months, terms). Hover a column for its value.

    <x-report.columns :bars="[['label' => 'Week 1', 'value' => 92, 'display' => '92%', 'current' => false], …]" :max="100" />
--}}
@props(['bars', 'tone' => 'brand', 'max' => null, 'label' => 'Chart'])

@php
    // [resting column, current or hovered column]
    $fill = match ($tone) {
        'naija' => ['bg-naija/55 group-hover:bg-naija', 'bg-naija'],
        'sky' => ['bg-sky/55 group-hover:bg-sky', 'bg-sky'],
        'gold' => ['bg-gold/50 group-hover:bg-gold', 'bg-gold'],
        default => ['bg-brand/55 group-hover:bg-brand-600', 'bg-brand-600'],
    };
    $top = max(1, $max ?? max(array_map(fn (array $bar) => (int) $bar['value'], $bars) ?: [0]));
    $every = max(1, (int) ceil(count($bars) / 12));
@endphp

@if ($bars === [])
    <p class="m-0 text-sm text-muted">Nothing recorded yet.</p>
@else
    <div {{ $attributes->class(['flex flex-col gap-1.5']) }}>
        <div class="flex h-44 items-end gap-[3px] border-b border-line print:h-32" role="img" aria-label="{{ $label }}: {{ collect($bars)->map(fn ($bar) => $bar['label'].' '.$bar['display'])->join(', ') }}">
            @foreach ($bars as $bar)
                @php($height = $bar['value'] > 0 ? max(2, round($bar['value'] / $top * 100, 1)) : 0)
                <div class="group relative flex h-full min-w-0 flex-1 flex-col justify-end" title="{{ $bar['label'] }}: {{ $bar['display'] }}">
                    <span class="pointer-events-none absolute -top-1 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full rounded-md bg-ink px-2 py-1 text-[11px] font-semibold whitespace-nowrap text-white group-hover:block">{{ $bar['label'] }} · {{ $bar['display'] }}</span>
                    <span @class(['block rounded-t-[4px] transition-colors', ($bar['current'] ?? false) ? $fill[1] : $fill[0]]) style="height: {{ $height }}%"></span>
                </div>
            @endforeach
        </div>
        <div class="flex gap-[3px]" aria-hidden="true">
            @foreach ($bars as $index => $bar)
                <span class="min-w-0 flex-1 truncate text-center text-[10px] text-muted">{{ $index % $every === 0 ? $bar['label'] : '' }}</span>
            @endforeach
        </div>
    </div>
@endif
