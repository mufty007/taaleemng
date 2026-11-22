{{--
    A percentage in a table cell, with a short bar. Below :alert-below it turns red.

    <x-report.meter :value="$row['rate']" :alert-below="80" />
--}}
@props(['value', 'alertBelow' => null, 'tone' => 'brand'])

@php
    $alert = $value !== null && $alertBelow !== null && $value < $alertBelow;
    $bar = $alert ? 'bg-danger' : match ($tone) {
        'naija' => 'bg-naija',
        'sky' => 'bg-sky',
        default => 'bg-brand',
    };
@endphp

@if ($value === null)
    <span class="text-muted">—</span>
@else
    <span {{ $attributes->class(['inline-flex items-center justify-end gap-2']) }}>
        <span class="hidden h-1.5 w-14 overflow-hidden rounded-full bg-chip sm:block print:hidden" aria-hidden="true"><span class="block h-full rounded-full {{ $bar }}" style="width: {{ max(0, min(100, $value)) }}%"></span></span>
        <span @class(['w-11 text-right font-semibold tabular-nums print:w-auto', 'text-danger' => $alert])>{{ $value }}%</span>
    </span>
@endif
