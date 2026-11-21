<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's go at a quiz: answers by question id, and the mark.
 *
 * @property Carbon $started_at
 * @property Carbon|null $submitted_at
 * @property array<int|string, mixed>|null $answers
 */
#[Fillable(['quiz_id', 'student_id', 'started_at', 'submitted_at', 'answers', 'score', 'max_score'])]
class QuizAttempt extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'answers' => 'array',
            'score' => 'float',
            'max_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function percent(): ?int
    {
        return $this->score === null || $this->max_score === 0 ? null : (int) round($this->score / $this->max_score * 100);
    }

    /**
     * When time runs out for a timed quiz, or null if it has no time limit.
     */
    public function deadline(Quiz $quiz): ?Carbon
    {
        $timed = $quiz->duration_minutes ? $this->started_at->copy()->addMinutes($quiz->duration_minutes) : null;

        return match (true) {
            $timed && $quiz->closes_at => $timed->min($quiz->closes_at),
            default => $timed ?? $quiz->closes_at,
        };
    }
}
