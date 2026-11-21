<?php

namespace App\Models;

use App\Enums\EnrolmentStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place in a class arm for one academic session.
 * Results, fees and attendance hang off this, so history stays correct after promotion.
 *
 * @property EnrolmentStatus $status
 */
#[Fillable(['student_id', 'academic_session_id', 'class_arm_id', 'status'])]
class Enrolment extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrolmentStatus::class,
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
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<ClassArm, $this>
     */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }
}
