<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's answer to an assignment, and the teacher's mark.
 *
 * @property Carbon $submitted_at
 * @property Carbon|null $marked_at
 * @property list<array{path: string, name: string, size: int, mime: string}>|null $files
 */
#[Fillable(['assignment_id', 'student_id', 'body', 'files', 'submitted_at', 'score', 'feedback', 'marked_by', 'marked_at'])]
class AssignmentSubmission extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'marked_at' => 'datetime',
            'score' => 'float',
            'files' => 'array',
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
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function isLate(Assignment $assignment): bool
    {
        return $this->submitted_at->gt($assignment->due_at);
    }
}
