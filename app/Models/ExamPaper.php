<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One paper on the exam timetable: a class level sits a subject at a time, in a venue, with an invigilator.
 *
 * @property Carbon $starts_at
 */
#[Fillable(['term_id', 'class_level_id', 'subject_id', 'starts_at', 'duration_minutes', 'venue', 'invigilator_id', 'notes'])]
class ExamPaper extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ClassLevel, $this>
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function invigilator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'invigilator_id');
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    /**
     * "9:00 – 11:00am"
     */
    public function times(): string
    {
        return $this->starts_at->format('g:i').' – '.$this->endsAt()->format('g:ia');
    }

    /**
     * Papers that overlap the time between $from and $to.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where('starts_at', '<', $to)
            ->whereRaw('DATE_ADD(starts_at, INTERVAL duration_minutes MINUTE) > ?', [$from]);
    }
}
