<?php

namespace App\Models;

use App\Enums\SpecialExamKind;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A mock or practice exam (WAEC, NECO, JAMB, BECE, Common Entrance) sat on one day,
 * scored apart from termly results. Students and parents see it once it is published.
 *
 * @property SpecialExamKind $kind
 * @property Carbon $held_on
 * @property Carbon|null $published_at
 */
#[Fillable(['academic_session_id', 'title', 'kind', 'held_on', 'pass_mark', 'published_at', 'created_by'])]
class SpecialExam extends Model
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
            'kind' => SpecialExamKind::class,
            'held_on' => 'date',
            'pass_mark' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return HasMany<SpecialExamPaper, $this>
     */
    public function papers(): HasMany
    {
        return $this->hasMany(SpecialExamPaper::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<SpecialExamCandidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(SpecialExamCandidate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
