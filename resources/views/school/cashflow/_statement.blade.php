{{-- Month by month and by category. Shared by the cashflow page and its printout. --}}
@use('App\Support\Money')

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div>
        <h2 class="m-0 mb-2 text-base font-semibold">Month by month</h2>
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b-2 border-brand-600 text-xs font-semibold text-muted uppercase">
                    <th scope="col" class="py-2 pr-3">Month</th>
                    <th scope="col" class="px-3 py-2 text-right">In</th>
                    <th scope="col" class="px-3 py-2 text-right">Out</th>
                    <th scope="col" class="py-2 pl-3 text-right">Net</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($months as $month)
                    <tr class="border-b border-line">
                        <th scope="row" class="py-2 pr-3 text-left font-medium">{{ $month['month'] }}</th>
                        <td class="px-3 py-2 text-right font-mono text-naija-700">{{ Money::format($month['in']) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-danger">{{ Money::format($month['out']) }}</td>
                        <td @class(['py-2 pl-3 text-right font-mono font-semibold', 'text-danger' => $month['net'] < 0])>{{ Money::format($month['net']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-muted">Nothing yet.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" class="py-2 pr-3 text-left">Total</th>
                    <td class="px-3 py-2 text-right font-mono font-bold text-naija-700">{{ Money::format($income) }}</td>
                    <td class="px-3 py-2 text-right font-mono font-bold text-danger">{{ Money::format($spending) }}</td>
                    <td @class(['py-2 pl-3 text-right font-mono font-bold', 'text-danger' => $net < 0])>{{ Money::format($net) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex flex-col gap-5">
        <div>
            <h2 class="m-0 mb-2 text-base font-semibold">Spending by category</h2>
            @php($top = max(1, (int) $byCategory->max('total')))
            <ul class="m-0 flex list-none flex-col gap-1.5 p-0 text-sm">
                @forelse ($byCategory as $row)
                    <li class="grid grid-cols-[minmax(0,11rem)_minmax(0,1fr)_auto] items-center gap-3">
                        <span class="truncate">{{ $row['category']->label() }}</span>
                        <span class="h-2 overflow-hidden rounded-full bg-chip"><span class="block h-full rounded-full bg-danger/70" style="width: {{ round($row['total'] / $top * 100) }}%"></span></span>
                        <span class="font-mono text-xs">{{ Money::format($row['total']) }}</span>
                    </li>
                @empty
                    <li class="text-muted">No expenses recorded.</li>
                @endforelse
            </ul>
        </div>
        <div>
            <h2 class="m-0 mb-2 text-base font-semibold">Fees in, by how parents paid</h2>
            @php($topMethod = max(1, max($byMethod ?: [0])))
            <ul class="m-0 flex list-none flex-col gap-1.5 p-0 text-sm">
                @forelse ($byMethod as $method => $amount)
                    <li class="grid grid-cols-[minmax(0,11rem)_minmax(0,1fr)_auto] items-center gap-3">
                        <span class="truncate">{{ $method }}</span>
                        <span class="h-2 overflow-hidden rounded-full bg-chip"><span class="block h-full rounded-full bg-naija" style="width: {{ round($amount / $topMethod * 100) }}%"></span></span>
                        <span class="font-mono text-xs">{{ Money::format($amount) }}</span>
                    </li>
                @empty
                    <li class="text-muted">No fees collected.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
