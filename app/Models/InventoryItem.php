<?php

namespace App\Models;

use App\Enums\InventoryCategory;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something kept in the school store: uniforms, books, stationery…
 *
 * @property InventoryCategory $category
 */
#[Fillable([
    'name', 'sku', 'category', 'unit', 'quantity_on_hand', 'reorder_level', 'unit_cost', 'is_active',
])]
class InventoryItem extends Model
{
    use BelongsToSchool, HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'quantity_on_hand' => 'integer',
            'reorder_level' => 'integer',
            'unit_cost' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * At or below the reorder level (and a level was set).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where('reorder_level', '>', 0)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }

    public function isLowStock(): bool
    {
        return $this->reorder_level > 0 && $this->quantity_on_hand <= $this->reorder_level;
    }
}
