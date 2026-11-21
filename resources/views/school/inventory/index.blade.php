@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Inventory">
    <x-page-header kicker="Finance" title="Inventory" description="Uniforms, textbooks and store items, with stock levels and a history of every movement.">
        <x-slot:actions>
            @if ($lowCount > 0)
                <x-button variant="soft" icon="triangle-alert" :href="route('school.inventory.index', ['low' => 1])">{{ $lowCount }} low stock</x-button>
            @endif
            @if ($canManage)
                <x-button variant="brand" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-item')">Add item</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('school.inventory.index', array_filter(['search' => $search ?: null, 'low' => $lowOnly ? 1 : null])) }}" @class(['rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => ! $category, 'border-line bg-white text-muted hover:text-ink' => $category])>All</a>
        @foreach ($categories as $option)
            <a href="{{ route('school.inventory.index', array_filter(['category' => $option->value, 'search' => $search ?: null, 'low' => $lowOnly ? 1 : null])) }}" @class(['inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => $category === $option, 'border-line bg-white text-muted hover:text-ink' => $category !== $option])>
                <x-dynamic-component :component="'lucide-'.$option->icon()" class="size-3.5" aria-hidden="true" />
                {{ $option->label() }}
            </a>
        @endforeach
    </div>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4" role="search">
            @if ($category)<input type="hidden" name="category" value="{{ $category->value }}">@endif
            @if ($lowOnly)<input type="hidden" name="low" value="1">@endif
            <label for="search" class="sr-only">Search store</label>
            <div class="relative min-w-[220px] flex-1">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name or SKU" class="field pl-10">
            </div>
            <x-button variant="secondary">Search</x-button>
            @if ($lowOnly)
                <a href="{{ route('school.inventory.index', array_filter(['category' => $category?->value, 'search' => $search ?: null])) }}" class="text-sm font-semibold text-brand-600">Show all stock</a>
            @endif
        </form>

        @if ($items->isEmpty())
            <x-empty-state icon="package" title="Nothing in the store yet" body="Add uniforms, textbooks and other items, then record stock in and out." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3">Item</th>
                            <th scope="col" class="px-3 py-3">Category</th>
                            <th scope="col" class="px-3 py-3 text-right">On hand</th>
                            <th scope="col" class="px-3 py-3 text-right">Unit cost</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr class="border-t border-line">
                                <td class="px-5 py-3">
                                    <a href="{{ route('school.inventory.show', $item) }}" class="font-semibold hover:text-brand-700">{{ $item->name }}</a>
                                    <span class="mt-0.5 block text-xs text-muted">{{ $item->sku ? 'SKU '.$item->sku.' · ' : '' }}{{ $item->unit }}{{ $item->isLowStock() ? ' · low stock' : '' }}</span>
                                </td>
                                <td class="px-3 py-3 text-sm">{{ $item->category->label() }}</td>
                                <td class="px-3 py-3 text-right font-mono font-semibold @if($item->isLowStock()) text-danger @endif">{{ number_format($item->quantity_on_hand) }}</td>
                                <td class="px-3 py-3 text-right font-mono text-sm">{{ $item->unit_cost ? Money::format($item->unit_cost) : '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <x-button variant="ghost" size="sm" icon="arrow-right" :href="route('school.inventory.show', $item)">Open</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-3">{{ $items->links() }}</div>
        @endif
    </x-card>

    @if ($canManage)
        <x-drawer name="new-item" title="Add a store item" description="Catalogue first, then receive opening stock if you already have some." :show="$errors->item->isNotEmpty()" width="sm:max-w-lg">
            <form method="POST" action="{{ route('school.inventory.store') }}" class="flex flex-col gap-4">
                @csrf
                <x-field label="Name" name="name" bag="item" placeholder="e.g. Senior school shirt (white)" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="SKU" name="sku" bag="item" placeholder="UNI-SHIRT-W" />
                    <x-field label="Unit" name="unit" bag="item" value="piece" placeholder="piece, pack, litre…" required />
                </div>
                <x-select-field label="Category" name="category" :options="\App\Enums\InventoryCategory::options()" placeholder="Choose" bag="item" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Reorder level" name="reorder_level" bag="item" type="number" min="0" value="5" hint="Warn when stock hits this." />
                    <x-field label="Unit cost (₦)" name="unit_cost" bag="item" inputmode="decimal" placeholder="4,500" />
                </div>
                <x-field label="Opening quantity" name="opening_quantity" bag="item" type="number" min="0" value="0" />
                <x-button variant="brand" icon="check">Add item</x-button>
            </form>
        </x-drawer>
    @endif
</x-layouts.app>
