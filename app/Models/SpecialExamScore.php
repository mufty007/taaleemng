<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A candidate's mark on one paper of a special exam.
 */
#[Fillable(['special_exam_candidate_id', 'special_exam_paper_id', 'score', 'entered_by'])]
class SpecialExamScore extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    /**
     * @return BelongsTo<SpecialExamCandidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(SpecialExamCandidate::class, 'special_exam_candidate_id');
    }

    /**
     * @return BelongsTo<SpecialExamPaper, $this>
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(SpecialExamPaper::class, 'special_exam_paper_id');
    }
}
