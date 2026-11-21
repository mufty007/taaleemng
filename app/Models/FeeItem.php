<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the school charges for: Tuition, PTA levy, Books, Bus…
 */
#[Fillable(['name', 'is_optional', 'position'])]
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
