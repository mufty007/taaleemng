<?php

namespace App\Models;

use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A child applying for a place, with the parent who applied for them.
 *
 * @property ApplicantStatus $status
 * @property Gender $gender
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $exam_at
 * @property float|null $exam_score
 */
#[Fillable([
    'application_no', 'academic_session_id', 'class_level_id', 'status', 'source',
    'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'state_of_origin', 'religion', 'previous_school', 'address',
    'guardian_title', 'guardian_first_name', 'guardian_last_name', 'guardian_relationship', 'guardian_phone', 'guardian_email', 'guardian_occupation',
    'exam_at', 'exam_score', 'notes', 'student_id',
])]
class Applicant extends Model
{
    use BelongsToSchool, HasUlids;

    /**
     * Fields copied onto the student record on enrolment.
     */
    public const STUDENT_FIELDS = ['first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'state_of_origin', 'religion', 'previous_school', 'address'];

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
            'status' => ApplicantStatus::class,
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'exam_at' => 'datetime',
            'exam_score' => 'float',
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
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
    }

    public function registerName(): string
    {
        return trim(Str::upper($this->last_name).' '.$this->first_name.' '.($this->middle_name ?? ''));
    }

    public function guardianName(): string
    {
        return trim(($this->guardian_title ? $this->guardian_title.' ' : '').$this->guardian_first_name.' '.$this->guardian_last_name);
    }
}
