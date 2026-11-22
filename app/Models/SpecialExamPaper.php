<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One paper in a special exam, e.g. "Use of English" out of 100.
 */
#[Fillable(['special_exam_id', 'subject_id', 'name', 'max_score', 'position'])]
class SpecialExamPaper extends Model
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

    /**
     * @return BelongsTo<SpecialExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(SpecialExam::class, 'special_exam_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
