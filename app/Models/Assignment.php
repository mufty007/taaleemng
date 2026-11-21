<?php

namespace App\Models;

use App\Enums\ClassworkStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Homework or a project for one subject in one class, handed in online.
 *
 * @property ClassworkStatus $status
 * @property Carbon $due_at
 * @property list<array{path: string, name: string, size: int, mime: string}>|null $files
 * @property-read int $submissions_count
 * @property-read int $unmarked_count
 */
#[Fillable(['class_subject_id', 'term_id', 'created_by', 'title', 'instructions', 'files', 'due_at', 'max_score', 'status'])]
class Assignment extends Model
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
            'status' => ClassworkStatus::class,
            'due_at' => 'datetime',
            'max_score' => 'integer',
            'files' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isPastDue(): bool
    {
        return $this->due_at->isPast();
    }
}
