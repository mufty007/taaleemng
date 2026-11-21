<?php

namespace App\Models;

use App\Enums\ClassworkStatus;
use App\Enums\QuizKind;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An online quiz, class test or CBT exam for one subject in one class, marked automatically.
 *
 * @property QuizKind $kind
 * @property ClassworkStatus $status
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 */
#[Fillable(['class_subject_id', 'term_id', 'created_by', 'title', 'instructions', 'kind', 'duration_minutes', 'opens_at', 'closes_at', 'shuffle_questions', 'show_answers', 'status'])]
class Quiz extends Model
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
            'kind' => QuizKind::class,
            'status' => ClassworkStatus::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'duration_minutes' => 'integer',
            'shuffle_questions' => 'boolean',
            'show_answers' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<ClassSubject, $this>
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ClassworkStatus::Published
            && ($this->opens_at === null || $this->opens_at->isPast())
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    public function hasClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }
}
