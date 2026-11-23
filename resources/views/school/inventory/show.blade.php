@php
    use App\Support\Money;
@endphp

<x-layouts.app :title="$item->name">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.inventory.index') }}" class="hover:text-ink">Inventory</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $item->name }}</span>
    </div>

    <x-page-header kicker="{{ $item->category->label() }}" :title="$item->name" :description="($item->sku ? 'SKU '.$item->sku.' · ' : '').$item->unit.($item->isLowStock() ? ' · below reorder level' : '')">
        <x-slot:actions>
            @if ($item->isLowStock())
                <x-badge tone="danger">Low stock</x-badge>
            @endif
            @if ($canManage)
                <x-button variant="brand" icon="arrow-left-right" type="button" x-data x-on:click="$dispatch('open-drawer', 'stock-move')">Record movement</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 [&>*:first-child]:col-span-2 sm:[&>*:first-child]:col-span-1">
        <x-stat-card label="On hand" :value="number_format($item->quantity_on_hand)" icon="package" :tone="$item->isLowStock() ? 'danger' : 'naija'" :hint="'Reorder at '.$item->reorder_level" />
        <x-stat-card label="Unit cost" :value="$item->unit_cost ? Money::format($item->unit_cost) : '—'" icon="banknote" tone="gold" />
        <x-stat-card label="Stock value" :value="$item->unit_cost ? Money::format($item->unit_cost * $item->quantity_on_hand) : '—'" icon="wallet" tone="brand" />
    </div>

    <x-card :padding="false">
        <div class="border-b border-line px-5 py-3">
            <h2 class="m-0 text-base font-semibold">Recent movements</h2>
        </div>
        @if ($item->movements->isEmpty())
            <x-empty-state icon="arrow-left-right" title="No movements yet" body="Receive stock or issue items to see the history here." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3">When</th>
                            <th scope="col" class="px-3 py-3">Type</th>
                            <th scope="col" class="px-3 py-3 text-right">Qty</th>
                            <th scope="col" class="px-3 py-3 text-right">Balance</th>
                            <th scope="col" class="px-5 py-3">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($item->movements as $movement)
                            <tr class="border-t border-line">
                                <td class="px-5 py-3 text-sm whitespace-nowrap">{{ $movement->moved_at->format('j M Y, g:ia') }}</td>
                                <td class="px-3 py-3">
                                    <span class="font-medium">{{ $movement->type->label() }}</span>
                                    <span class="block text-xs text-muted">by {{ $movement->recorder?->name ?? 'store' }}{{ $movement->student ? ' · '.$movement->student->fullName() : '' }}</span>
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-semibold {{ $movement->type->increasesStock() || ($movement->type === \App\Enums\StockMovementType::Adjust && $movement->quantity > 0) ? 'text-naija-700' : 'text-danger' }}">
                                    {{ $movement->type === \App\Enums\StockMovementType::Adjust ? ($movement->quantity > 0 ? '+' : '').$movement->quantity : (($movement->type->increasesStock() ? '+' : '−').$movement->quantity) }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono">{{ number_format($movement->balance_after) }}</td>
                                <td class="px-5 py-3 text-sm text-muted">{{ $movement->note ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($canManage)
        <x-drawer name="stock-move" title="Record a movement" :description="'Update stock for '.$item->name.'.'" :show="$errors->move->isNotEmpty()" width="sm:max-w-lg">
            <form method="POST" action="{{ route('school.inventory.move', $item) }}" class="flex flex-col gap-4">
                @csrf
                <x-select-field label="Type" name="type" :options="$movementTypes" value="receive" bag="move" required />
                <x-field label="Quantity" name="quantity" bag="move" type="number" required hint="For adjustments, use a negative number to reduce stock." />
                <x-field label="Unit cost (₦)" name="unit_cost" bag="move" inputmode="decimal" hint="Optional. Updates the item cost when receiving stock." />
                <div class="flex flex-col gap-1.5">
                    <label for="student_id" class="text-sm font-semibold">Student <span class="font-normal text-muted">(sales only)</span></label>
                    <select id="student_id" name="student_id" class="field">
                        <option value="">—</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->fullName() }} · {{ $student->admission_no }}</option>
                        @endforeach
                    </select>
                    @error('student_id', 'move')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
                <x-field label="Note" name="note" bag="move" placeholder="e.g. From bookshop delivery" />
                <x-button variant="brand" icon="check">Save movement</x-button>
            </form>
        </x-drawer>
    @endif
</x-layouts.app>
