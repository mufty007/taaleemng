<?php

namespace App\Support\Dashboard;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\Term;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The school store at a glance for the store keeper: what is running out, what went
 * out today, and the latest movements. Values are at cost, in kobo.
 */
final class StorePulse
{
    public function __construct(public readonly ?Term $term) {}

    /**
     * @return array{items: int, value: int, low: int, out: int}
     */
    public function stock(): array
    {
        $items = InventoryItem::query()->active()->get(['id', 'quantity_on_hand', 'reorder_level', 'unit_cost']);

        return [
            'items' => $items->count(),
            'value' => (int) $items->sum(fn (InventoryItem $item): int => $item->quantity_on_hand * (int) $item->unit_cost),
            'low' => $items->filter(fn (InventoryItem $item): bool => $item->reorder_level > 0 && $item->quantity_on_hand <= $item->reorder_level)->count(),
            'out' => $items->where('quantity_on_hand', 0)->count(),
        ];
    }

    /**
     * Items at or below their reorder level, emptiest first.
     *
     * @return EloquentCollection<int, InventoryItem>
     */
    public function lowStock(int $limit = 6): EloquentCollection
    {
        return InventoryItem::query()
            ->active()
            ->lowStock()
            ->orderByRaw('quantity_on_hand / reorder_level')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Sales and issues on one day.
     *
     * @return array{sales: int, salesValue: int, sold: int, issued: int, received: int}
     */
    public function day(?Carbon $date = null): array
    {
        $date ??= today();
        $movements = StockMovement::query()
            ->whereBetween('moved_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->get(['type', 'quantity', 'unit_cost']);
        $sales = $movements->where('type', StockMovementType::Sale);

        return [
            'sales' => $sales->count(),
            'salesValue' => (int) $sales->sum(fn (StockMovement $movement): int => abs($movement->quantity) * (int) $movement->unit_cost),
            'sold' => (int) $sales->sum(fn (StockMovement $movement): int => abs($movement->quantity)),
            'issued' => (int) $movements->where('type', StockMovementType::Issue)->sum(fn (StockMovement $movement): int => abs($movement->quantity)),
            'received' => (int) $movements->where('type', StockMovementType::Receive)->sum(fn (StockMovement $movement): int => abs($movement->quantity)),
        ];
    }

    /**
     * Sales value this term, at the cost recorded with each sale.
     */
    public function termSales(): int
    {
        if ($this->term === null) {
            return 0;
        }

        return (int) StockMovement::query()
            ->where('type', StockMovementType::Sale)
            ->whereBetween('moved_at', [$this->term->starts_on->copy()->startOfDay(), $this->term->ends_on->copy()->endOfDay()])
            ->get(['quantity', 'unit_cost'])
            ->sum(fn (StockMovement $movement): int => abs($movement->quantity) * (int) $movement->unit_cost);
    }

    /**
     * Units sold or issued on each of the last few school days, oldest first.
     *
     * @return Collection<int, array{date: Carbon, out: int}>
     */
    public function outTrend(int $days = 10): Collection
    {
        $dates = collect();
        $date = today();

        while ($dates->count() < $days) {
            if (! $date->isWeekend()) {
                $dates->prepend($date->copy());
            }

            $date = $date->subDay();
        }

        $totals = StockMovement::query()
            ->whereIn('type', [StockMovementType::Sale, StockMovementType::Issue])
            ->whereBetween('moved_at', [$dates->first()->copy()->startOfDay(), today()->endOfDay()])
            ->get(['quantity', 'moved_at'])
            ->groupBy(fn (StockMovement $movement): string => $movement->moved_at->toDateString())
            ->map(fn (Collection $group): int => (int) $group->sum(fn (StockMovement $movement): int => abs($movement->quantity)));

        return $dates->map(fn (Carbon $day): array => ['date' => $day, 'out' => (int) ($totals[$day->toDateString()] ?? 0)])->values();
    }

    /**
     * @return EloquentCollection<int, StockMovement>
     */
    public function recent(int $limit = 8): EloquentCollection
    {
        return StockMovement::query()
            ->with(['item', 'student', 'recorder'])
            ->latest('moved_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
