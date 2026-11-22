{{-- Income & expenditure. Shared by the report screen and its printout. --}}
@use('App\Support\Money')

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="Fees in" :value="Money::format($stats['income'])" :hint="$stats['billed'] > 0 ? 'Against '.Money::format($stats['billed']).' billed' : 'Confirmed payments'" tone="naija" />
    <x-report.stat label="Spending out" :value="Money::format($stats['spending'])" :hint="number_format($stats['expense_count']).' expenses, voided ones left out'" tone="danger" />
    <x-report.stat label="Surplus" :value="Money::format($stats['net'])" :hint="$stats['net'] < 0 ? 'Spent more than came in' : 'Left over'" :tone="$stats['net'] < 0 ? 'danger' : 'brand'" />
    <x-report.stat label="Margin" :value="$stats['margin'] !== null ? $stats['margin'].'%' : '—'" hint="Of fees left after spending" tone="gold" />
</section>

<p class="m-0 text-sm text-muted">
    Covers {{ $period->from->format('j M Y') }} to {{ $period->to->format('j M Y') }}: from the day after the previous term closed, so fees paid in the holiday count towards the term they are for.
</p>

<x-report.panel title="Statement">
    @include('school.cashflow._statement', [
        'months' => $months,
        'income' => $stats['income'],
        'spending' => $stats['spending'],
        'net' => $stats['net'],
        'byCategory' => $categories,
        'byMethod' => $methods,
    ])
</x-report.panel>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="Largest spending" description="The ten biggest expenses in the period" :padding="false">
        @if ($largest->isEmpty())
            <x-empty-state icon="receipt" title="No expenses recorded" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0 text-sm">
                @foreach ($largest as $expense)
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $expense->description }}</span>
                            <span class="block text-xs text-muted">{{ $expense->spent_on->format('j M') }} · {{ $expense->category->label() }}@if ($expense->paid_to) · {{ $expense->paid_to }}@endif</span>
                        </span>
                        <span class="font-mono text-xs text-danger">{{ Money::format($expense->amount) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-report.panel>

    <x-report.panel title="Payroll runs" description="For reference. The salary payment itself is in spending, under Salaries & allowances." :padding="false">
        @if ($payroll->isEmpty())
            <x-empty-state icon="banknote" title="No payroll runs in this period" />
        @else
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-2.5">Month</th>
                        <th scope="col" class="px-3 py-2.5 text-right">Staff</th>
                        <th scope="col" class="px-3 py-2.5 text-right">Gross</th>
                        <th scope="col" class="px-5 py-2.5 text-right">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payroll as $run)
                        <tr class="border-t border-line">
                            <th scope="row" class="px-5 py-2.5 text-left font-medium">{{ $run->label() }} <span class="text-xs font-normal text-muted">· {{ $run->status->label() }}</span></th>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $run->headcount }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-xs">{{ Money::format($run->gross_total) }}</td>
                            <td class="px-5 py-2.5 text-right font-mono text-xs font-semibold">{{ Money::format($run->net_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-report.panel>
</div>

<x-report.panel title="Term on term" description="This term against the terms before it" :padding="false">
    @php($scale = max(1, ...array_map(fn ($row) => max($row['income'], $row['spending']), $comparison ?: [['income' => 0, 'spending' => 0]])))
    <div class="flex flex-wrap gap-x-5 gap-y-1 px-5 pt-4 text-xs text-muted">
        <span class="flex items-center gap-1.5"><span class="h-2 w-4 rounded-full bg-naija" aria-hidden="true"></span> Fees in</span>
        <span class="flex items-center gap-1.5"><span class="h-2 w-4 rounded-full bg-danger/70" aria-hidden="true"></span> Spending out</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Term</th>
                    <th scope="col" class="w-[30%] px-3 py-3"><span class="sr-only">Chart</span></th>
                    <th scope="col" class="px-3 py-3 text-right">In</th>
                    <th scope="col" class="px-3 py-3 text-right">Out</th>
                    <th scope="col" class="px-5 py-3 text-right">Surplus</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($comparison as $row)
                    <tr @class(['border-t border-line', 'bg-brand-50/60' => $row['current']])>
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['label'] }}</th>
                        <td class="px-3 py-2.5" aria-hidden="true">
                            <span class="flex flex-col gap-[2px]">
                                <span class="block h-1.5 rounded-full bg-naija" style="width: {{ round($row['income'] / $scale * 100, 1) }}%"></span>
                                <span class="block h-1.5 rounded-full bg-danger/70" style="width: {{ round($row['spending'] / $scale * 100, 1) }}%"></span>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs text-naija-700">{{ Money::format($row['income']) }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs text-danger">{{ Money::format($row['spending']) }}</td>
                        <td @class(['px-5 py-2.5 text-right font-mono text-xs font-semibold', 'text-danger' => $row['net'] < 0])>{{ Money::format($row['net']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-report.panel>
