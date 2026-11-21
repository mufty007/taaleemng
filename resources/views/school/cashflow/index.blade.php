@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Cashflow">
    <x-page-header kicker="Finance" title="Cashflow" :description="'Fees in and spending out for '.$period->label().', '.$period->from->format('j M').' – '.$period->to->format('j M Y').'. Holiday payments count towards the term they come before.'">
        <x-slot:actions>
            <form method="GET">
                <label for="term" class="sr-only">Term</label>
                <select id="term" name="term" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($period->term?->id === $id)>{{ $label }}</option>@endforeach
                </select>
            </form>
            <x-button data-native variant="secondary" icon="printer" :href="route('school.cashflow.print', ['term' => $period->term?->id])">Print statement</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-stagger>
        <x-stat-card label="Fees in" :value="Money::format($income)" icon="arrow-down-left" tone="naija" hint="Confirmed payments" :href="route('school.payments.index', ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()])" />
        <x-stat-card label="Spending out" :value="Money::format($spending)" icon="arrow-up-right" tone="danger" hint="Expenses, not counting voided ones" :href="route('school.expenses.index', ['term' => $period->term?->id])" />
        <x-stat-card label="Left over" :value="Money::format($net)" icon="scale" :tone="$net < 0 ? 'danger' : 'brand'" :hint="$income > 0 ? round($spending / $income * 100).'% of fees spent' : null" />
    </div>

    <x-card>
        @include('school.cashflow._statement')
    </x-card>
</x-layouts.app>
