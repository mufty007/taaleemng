<?php

namespace App\Models;

use App\Enums\TermStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property TermStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $mid_term_starts_on
 * @property Carbon|null $mid_term_ends_on
 * @property Carbon|null $next_term_begins_on
 * @property int|null $school_days
 */
#[Fillable([
    'academic_session_id', 'sequence', 'name', 'starts_on', 'ends_on', 'mid_term_starts_on',
    'mid_term_ends_on', 'next_term_begins_on', 'school_days', 'status', 'is_current',
])]
class Term extends Model
{
    use BelongsToSchool;

    public const NAMES = [1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'mid_term_starts_on' => 'date',
            'mid_term_ends_on' => 'date',
            'next_term_begins_on' => 'date',
            'school_days' => 'integer',
            'status' => TermStatus::class,
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public static function current(): ?self
    {
        return static::query()->with('academicSession')->where('is_current', true)->first();
    }

    /**
     * Every term, newest first, labelled for a picker ("Second Term · 2026/2027").
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (AcademicSession::query()->with('terms')->orderByDesc('starts_on')->get() as $session) {
            foreach ($session->terms->sortByDesc('sequence') as $term) {
                $options[$term->id] = $term->name.' · '.$session->name;
            }
        }

        return $options;
    }

    /**
     * Whether this is the last term of its session (the one with cumulative results and promotion).
     */
    public function isFinal(): bool
    {
        return ! static::query()->where('academic_session_id', $this->academic_session_id)->where('sequence', '>', $this->sequence)->exists();
    }

    public function isClosed(): bool
    {
        return $this->status === TermStatus::Closed;
    }

    /**
     * Which week of term a date falls in (1-based), or null outside the term.
     */
    public function weekOf(Carbon $date): ?int
    {
        if ($date->lt($this->starts_on) || $date->gt($this->ends_on)) {
            return null;
        }

        return intdiv((int) $this->starts_on->startOfWeek()->diffInDays($date), 7) + 1;
    }
}
