<?php

namespace App\Models;

use App\Enums\PromotionDecision;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Everything on a student's report card that is not a score: attendance,
 * trait ratings, comments and (third term) the promotion decision.
 *
 * @property array<int, int>|null $traits trait id => rating 1–5
 * @property PromotionDecision|null $promotion_decision
 */
#[Fillable(['term_id', 'enrolment_id', 'days_present', 'traits', 'teacher_comment', 'principal_comment', 'promotion_decision', 'updated_by'])]
class TermReport extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_present' => 'integer',
            'traits' => 'array',
            'promotion_decision' => PromotionDecision::class,
        ];
    }

    /**
     * @return BelongsTo<Enrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function isComplete(int $traitCount): bool
    {
        return filled($this->teacher_comment) && $this->days_present !== null && count($this->traits ?? []) >= $traitCount;
    }
}
