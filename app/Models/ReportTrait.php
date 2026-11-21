<?php

namespace App\Models;

use App\Enums\TraitCategory;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A behaviour or skill rated 1–5 on the report card, e.g. Punctuality or Handwriting.
 *
 * @property TraitCategory $category
 */
#[Fillable(['name', 'category', 'position'])]
class ReportTrait extends Model
{
    use BelongsToSchool;

    public const RATINGS = [5 => 'Excellent', 4 => 'Very good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TraitCategory::class,
            'position' => 'integer',
        ];
    }
}
