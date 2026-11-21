<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the school charges for: Tuition, PTA levy, Books, Bus…
 * A paused item stays on old invoices but is left off the schedule and new invoices.
 */
#[Fillable(['name', 'is_optional', 'is_active', 'position'])]
class FeeItem extends Model
{
    use BelongsToSchool;

    public const NIGERIAN_DEFAULTS = ['Tuition', 'Development levy', 'PTA levy', 'Books & stationery', 'Exam fee', 'Sports & clubs'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_optional' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<FeeStructure, $this>
     */
    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}
