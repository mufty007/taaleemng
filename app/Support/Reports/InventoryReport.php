<?php

namespace App\Support\Reports;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\Term;
use App\Support\Finance\CashflowPeriod;
use App\Support\Money;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The school store: what is on the shelves and what it is worth (at cost), what needs
 * reordering, and what was received, issued and sold during a term.
 */
final class InventoryReport implements SchoolReport
{
    /** @var Collection<int, InventoryItem>|null */
    private ?Collection $items = null;

    /** @var array<int, array<string, int>>|null item id => movement type => quantity */
    private ?array $moved = null;

    private CashflowPeriod $period;

    public function __construct(public readonly Term $term)
    {
        $this->period = CashflowPeriod::forTerm($term);
    }

    public function periodLabel(): string
    {
        return $this->term->name.' · '.$this->term->academicSession->name;
    }

    public function figures(): array
    {
        $items = $this->items();
        $active = $items->where('is_active', true);
        $movements = $this->movements();

        return [
            'term' => $this->term,
            'period' => $this->period,
            'stats' => [
                'items' => $active->count(),
                'units' => (int) $active->sum('quantity_on_hand'),
                'value' => (int) $active->sum(fn (InventoryItem $item) => self::value($item)),
                'low' => $active->filter(fn (InventoryItem $item) => $item->isLowStock() && $item->quantity_on_hand > 0)->count(),
                'out' => $active->where('quantity_on_hand', 0)->count(),
                'uncosted' => $active->whereNull('unit_cost')->count(),
            ],
            'categories' => $active->groupBy(fn (InventoryItem $item) => $item->category->label())
                ->map(fn (Collection $group, string $category) => [
                    'category' => $category,
                    'items' => $group->count(),
                    'units' => (int) $group->sum('quantity_on_hand'),
                    'value' => (int) $group->sum(fn (InventoryItem $item) => self::value($item)),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'movements' => collect(StockMovementType::cases())
                ->map(function (StockMovementType $type) use ($movements): array {
                    $group = $movements->where('type', $type);

                    return [
                        'type' => $type,
                        'count' => $group->count(),
                        'units' => (int) $group->sum(fn (StockMovement $movement) => abs($movement->quantity)),
                        'value' => (int) $group->sum(fn (StockMovement $movement) => abs($movement->quantity) * (int) $movement->unit_cost),
                    ];
                })
                ->all(),
            'busiest' => $items
                ->map(fn (InventoryItem $item) => ['item' => $item, ...$this->movedFor($item)])
                ->filter(fn (array $row) => $row['out'] > 0)
                ->sortByDesc('out')
                ->take(10)
                ->values(),
            'reorder' => $active
                ->filter(fn (InventoryItem $item) => $item->isLowStock())
                ->sortBy(fn (InventoryItem $item) => $item->quantity_on_hand - $item->reorder_level)
                ->values(),
        ];
    }

    public function csv(): StreamedResponse
    {
        $rows = $this->items()->map(function (InventoryItem $item): array {
            $moved = $this->movedFor($item);

            return [
                $item->name,
                $item->sku,
                $item->category->label(),
                $item->unit,
                $item->quantity_on_hand,
                $item->reorder_level ?: null,
                $item->unit_cost !== null ? Money::toNaira($item->unit_cost) : null,
                Money::toNaira(self::value($item)),
                $moved['received'],
                $moved['issued'],
                $moved['sold'],
                $item->is_active ? 'Active' : 'Archived',
            ];
        });

        return CsvDownload::make(
            'stock-'.str($this->term->name.' '.$this->term->academicSession->name)->slug().'.csv',
            ['Item', 'SKU', 'Category', 'Unit', 'On hand', 'Reorder level', 'Unit cost (₦)', 'Stock value (₦)', 'Received this term', 'Issued this term', 'Sold this term', 'Status'],
            $rows,
        );
    }

    /**
     * Stock value at cost (kobo).
     */
    private static function value(InventoryItem $item): int
    {
        return $item->quantity_on_hand * (int) $item->unit_cost;
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    private function items(): Collection
    {
        return $this->items ??= InventoryItem::query()->orderBy('category')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, StockMovement>
     */
    private function movements(): Collection
    {
        return StockMovement::query()
            ->whereBetween('moved_at', [$this->period->from->copy()->startOfDay(), $this->period->to->copy()->endOfDay()])
            ->get(['id', 'inventory_item_id', 'type', 'quantity', 'unit_cost']);
    }

    /**
     * @return array{received: int, issued: int, sold: int, out: int}
     */
    private function movedFor(InventoryItem $item): array
    {
        $this->moved ??= $this->movements()
            ->groupBy('inventory_item_id')
            ->map(fn (Collection $group) => $group->groupBy(fn (StockMovement $movement) => $movement->type->value)
                ->map(fn (Collection $byType) => (int) $byType->sum(fn (StockMovement $movement) => abs($movement->quantity)))
                ->all())
            ->all();

        $moved = $this->moved[$item->id] ?? [];
        $issued = $moved[StockMovementType::Issue->value] ?? 0;
        $sold = $moved[StockMovementType::Sale->value] ?? 0;

        return [
            'received' => $moved[StockMovementType::Receive->value] ?? 0,
            'issued' => $issued,
            'sold' => $sold,
            'out' => $issued + $sold,
        ];
    }
}
