<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One part of a subject's 100 marks, e.g. CA1 (20), CA2 (20), Exam (60).
 */
#[Fillable(['name', 'short_name', 'max_score', 'position'])]
class AssessmentComponent extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'position' => 'integer',
        ];
    }
}
