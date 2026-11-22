{{-- Fees & collections. Shared by the report screen and its printout. --}}
@use('App\Support\Money')

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="Billed this term" :value="Money::format($stats['billed'])" :hint="number_format($stats['invoices']).' invoices'" />
    <x-report.stat label="Collected" :value="Money::format($stats['paid'])" :hint="$stats['rate'] !== null ? $stats['rate'].'% of what was billed' : null" tone="naija" />
    <x-report.stat label="Outstanding" :value="Money::format($stats['outstanding'])" :hint="number_format($stats['part_paid'] + $stats['unpaid']).' invoices not fully paid'" tone="danger" />
    <x-report.stat label="Cash received" :value="Money::format($stats['received'])" :hint="number_format($stats['receipts']).' payments, '.$period->from->format('j M').' – '.$period->to->format('j M')" tone="gold" />
</section>

<x-report.panel title="Invoice status">
    @php($statusTotal = max(1, $stats['invoices']))
    <div class="flex h-3 overflow-hidden rounded-full bg-chip" role="img" aria-label="{{ $stats['fully_paid'] }} paid, {{ $stats['part_paid'] }} part-paid, {{ $stats['unpaid'] }} unpaid">
        <span class="block h-full bg-naija" style="width: {{ $stats['fully_paid'] / $statusTotal * 100 }}%"></span>
        <span class="block h-full border-l-2 border-white bg-gold/70" style="width: {{ $stats['part_paid'] / $statusTotal * 100 }}%"></span>
        <span class="block h-full border-l-2 border-white bg-danger/70" style="width: {{ $stats['unpaid'] / $statusTotal * 100 }}%"></span>
    </div>
    <ul class="m-0 mt-3 flex list-none flex-wrap gap-x-6 gap-y-2 p-0 text-sm">
        <li class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-naija" aria-hidden="true"></span> Paid in full <strong class="tabular-nums">{{ number_format($stats['fully_paid']) }}</strong></li>
        <li class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-gold/70" aria-hidden="true"></span> Part-paid <strong class="tabular-nums">{{ number_format($stats['part_paid']) }}</strong></li>
        <li class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-danger/70" aria-hidden="true"></span> Unpaid <strong class="tabular-nums">{{ number_format($stats['unpaid']) }}</strong></li>
        @if ($stats['not_invoiced'] > 0)
            <li class="flex items-center gap-2 text-gold"><x-lucide-triangle-alert class="size-4" aria-hidden="true" /> {{ number_format($stats['not_invoiced']) }} {{ Str::plural('student', $stats['not_invoiced']) }} on roll with no invoice for this term</li>
        @endif
    </ul>
</x-report.panel>

<x-report.panel title="By class" description="This term’s invoices, class by class" :padding="false" :keep="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Class</th>
                    <th scope="col" class="px-3 py-3 text-right">Invoices</th>
                    <th scope="col" class="px-3 py-3 text-right">Billed</th>
                    <th scope="col" class="px-3 py-3 text-right">Collected</th>
                    <th scope="col" class="px-3 py-3 text-right">Outstanding</th>
                    <th scope="col" class="px-3 py-3 text-right">Paid · owing</th>
                    <th scope="col" class="px-5 py-3 text-right">Collected %</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $row)
                    <tr class="border-t border-line break-inside-avoid">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['arm']->displayName() }}</th>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['invoices'] }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs">{{ Money::format($row['billed']) }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs text-naija-700">{{ Money::format($row['paid']) }}</td>
                        <td @class(['px-3 py-2.5 text-right font-mono text-xs', 'text-danger' => $row['outstanding'] > 0])>{{ Money::format($row['outstanding']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['fully_paid'] }} · {{ $row['owing'] }}</td>
                        <td class="px-5 py-2.5 text-right"><x-report.meter :value="$row['rate']" :alert-below="50" tone="naija" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-6 text-muted">No invoices have been generated for this term yet.</td></tr>
                @endforelse
            </tbody>
            @if ($classes->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-line">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">Whole school</th>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($stats['invoices']) }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs font-bold">{{ Money::format($stats['billed']) }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs font-bold text-naija-700">{{ Money::format($stats['paid']) }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-xs font-bold text-danger">{{ Money::format($stats['outstanding']) }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $stats['fully_paid'] }} · {{ $stats['part_paid'] + $stats['unpaid'] }}</td>
                        <td class="px-5 py-2.5 text-right"><x-report.meter :value="$stats['rate']" tone="naija" /></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-report.panel>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="By fee item" description="What each item adds up to on this term’s invoices" :padding="false">
        @if ($items->isEmpty())
            <x-empty-state icon="receipt" title="No invoice lines yet" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0 text-sm">
                @foreach ($items as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $row['name'] }}</span>
                            <span class="block text-xs text-muted">{{ number_format($row['students']) }} {{ Str::plural('invoice', $row['students']) }}@if ($row['discount']) · discount or waiver @endif</span>
                        </span>
                        <span @class(['font-mono text-xs', 'text-danger' => $row['discount']])>{{ Money::format($row['amount']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-report.panel>

    <x-report.panel title="How old the debt is" description="Every unpaid balance in the school today, all terms" :padding="false">
        <x-slot:actions>
            <a href="{{ route('school.debtors.index') }}" class="font-semibold text-brand-600 hover:text-ink">Debtors list</a>
        </x-slot:actions>
        @php($oldest = max(1, ...array_column($ageing, 'amount')))
        <ul class="m-0 list-none divide-y divide-line p-0 text-sm">
            @foreach ($ageing as $bucket)
                <li class="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)_auto] items-center gap-3 px-5 py-2.5">
                    <span class="truncate">{{ $bucket['label'] }}</span>
                    <span class="h-2 overflow-hidden rounded-full bg-chip" aria-hidden="true"><span @class(['block h-full rounded-full', $loop->first ? 'bg-gold/70' : 'bg-danger/70']) style="width: {{ $bucket['amount'] > 0 ? max(2, round($bucket['amount'] / $oldest * 100)) : 0 }}%"></span></span>
                    <span class="text-right font-mono text-xs">{{ Money::format($bucket['amount']) }} <span class="text-muted">· {{ $bucket['invoices'] }}</span></span>
                </li>
            @endforeach
        </ul>
    </x-report.panel>
</div>

<x-report.panel title="Money received each week" :description="'Confirmed payments from '.$period->from->format('j M').' to '.$period->to->format('j M Y').', whichever term they were for'">
    <x-report.columns
        :bars="array_map(fn ($week) => ['label' => $week['label'], 'value' => $week['amount'], 'display' => Money::format($week['amount'])], $weeks)"
        tone="naija"
        label="Fees received by week"
    />
</x-report.panel>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="How parents paid">
        <x-report.bars :rows="$methods" money tone="naija" empty="No payments in this period." />
    </x-report.panel>

    <x-report.panel title="Who received the money" description="Payments recorded by each member of staff" :padding="false">
        @if ($recorders === [])
            <x-empty-state icon="user-round" title="No payments in this period" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0 text-sm">
                @foreach ($recorders as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span><span class="font-medium">{{ $row['name'] }}</span> <span class="text-xs text-muted">· {{ number_format($row['payments']) }} {{ Str::plural('payment', $row['payments']) }}</span></span>
                        <span class="font-mono text-xs">{{ Money::format($row['amount']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-report.panel>
</div>

<x-report.panel title="Largest balances this term" :padding="false">
    @if ($largest->isEmpty())
        <x-empty-state icon="badge-check" title="Nothing owed on this term’s invoices" />
    @else
        <ul class="m-0 list-none divide-y divide-line p-0 text-sm">
            @foreach ($largest as $invoice)
                <li class="flex items-center gap-3 px-5 py-2.5">
                    <span class="min-w-0 flex-1">
                        <a href="{{ route('school.invoices.show', $invoice) }}" class="block truncate font-semibold hover:text-brand-600">{{ $invoice->student->registerName() }}</a>
                        <span class="block text-xs text-muted">{{ $invoice->enrolment->classArm->displayName() }} · {{ $invoice->invoice_no }} · paid {{ Money::format($invoice->amount_paid) }} of {{ Money::format($invoice->total) }}</span>
                    </span>
                    <span class="font-mono text-xs font-semibold text-danger">{{ Money::format($invoice->balance()) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-report.panel>
