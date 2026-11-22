{{-- Stores & inventory. Shared by the report screen and its printout. --}}
@use('App\Support\Money')

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="Stock value" :value="Money::format($stats['value'])" :hint="$stats['uncosted'] > 0 ? 'At cost · '.$stats['uncosted'].' items have no unit cost' : 'At cost'" />
    <x-report.stat label="Items in store" :value="number_format($stats['items'])" :hint="number_format($stats['units']).' units on hand'" tone="sky" />
    <x-report.stat label="Running low" :value="number_format($stats['low'])" hint="At or below reorder level" tone="gold" />
    <x-report.stat label="Out of stock" :value="number_format($stats['out'])" tone="danger" />
</section>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="By category" :padding="false">
        @if ($categories === [])
            <x-empty-state icon="package" title="No items in the store yet" />
        @else
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-2.5">Category</th>
                        <th scope="col" class="px-3 py-2.5 text-right">Items</th>
                        <th scope="col" class="px-3 py-2.5 text-right">Units</th>
                        <th scope="col" class="px-5 py-2.5 text-right">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $row)
                        <tr class="border-t border-line">
                            <th scope="row" class="px-5 py-2.5 text-left font-medium">{{ $row['category'] }}</th>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['items'] }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['units']) }}</td>
                            <td class="px-5 py-2.5 text-right font-mono text-xs">{{ Money::format($row['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-report.panel>

    <x-report.panel title="Movements this term" :description="$period->from->format('j M').' – '.$period->to->format('j M Y').', valued at cost'" :padding="false">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-2.5">Movement</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Entries</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Units</th>
                    <th scope="col" class="px-5 py-2.5 text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $row)
                    <tr class="border-t border-line">
                        <th scope="row" class="px-5 py-2.5 text-left font-medium">{{ $row['type']->label() }}</th>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['count'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['units']) }}</td>
                        <td class="px-5 py-2.5 text-right font-mono text-xs">{{ Money::format($row['value']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-report.panel>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="Fastest moving" description="Most units issued or sold this term" :padding="false">
        @if ($busiest->isEmpty())
            <x-empty-state icon="package-open" title="Nothing issued or sold this term" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0 text-sm">
                @foreach ($busiest as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $row['item']->name }}</span>
                            <span class="block text-xs text-muted">{{ $row['issued'] }} issued · {{ $row['sold'] }} sold · {{ $row['received'] }} received</span>
                        </span>
                        <span class="text-right text-xs text-muted tabular-nums"><strong class="text-sm text-ink">{{ number_format($row['out']) }}</strong> {{ Str::plural($row['item']->unit, $row['out']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-report.panel>

    <x-report.panel title="To reorder" description="At or below the reorder level, emptiest first" :padding="false">
        <x-slot:actions>
            <a href="{{ route('school.inventory.index') }}" class="font-semibold text-brand-600 hover:text-ink">Open store</a>
        </x-slot:actions>
        @if ($reorder->isEmpty())
            <x-empty-state icon="badge-check" title="Nothing needs reordering" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0 text-sm">
                @foreach ($reorder as $item)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $item->name }}</span>
                            <span class="block text-xs text-muted">{{ $item->category->label() }} · reorder at {{ $item->reorder_level }}</span>
                        </span>
                        @if ($item->quantity_on_hand === 0)
                            <x-badge tone="danger">Out of stock</x-badge>
                        @else
                            <x-badge tone="gold">{{ $item->quantity_on_hand }} left</x-badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-report.panel>
</div>
