@php
    use App\Support\Money;
@endphp

<x-layouts.print :title="'Cashflow '.$period->label()" document="Cashflow statement" :school="$currentSchool">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $period->label() }}</div>
        <div class="text-sm text-muted">{{ $period->from->format('j M Y') }} – {{ $period->to->format('j M Y') }}</div>
    </x-slot:meta>

    <section class="my-5 grid grid-cols-3 gap-3">
        @foreach (['Fees in' => $income, 'Spending out' => $spending, 'Left over' => $net] as $label => $amount)
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
                <div class="text-xs text-brand-700">{{ $label }}</div>
                <div @class(['text-lg font-bold', 'text-danger' => $amount < 0])>{{ Money::format($amount) }}</div>
            </div>
        @endforeach
    </section>

    @include('school.cashflow._statement')

    <div class="mt-10 grid grid-cols-2 gap-10 text-sm text-muted">
        <span class="border-t border-dashed border-line pt-1 text-center">Bursar</span>
        <span class="border-t border-dashed border-line pt-1 text-center">Proprietor / Principal</span>
    </div>
</x-layouts.print>
