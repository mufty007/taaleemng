<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student sitting a special exam, with the class they were in when it was set.
 */
#[Fillable(['special_exam_id', 'student_id', 'class_arm_id'])]
class SpecialExamCandidate extends Model
{
    use BelongsToSchool;

    /**
     * @return BelongsTo<SpecialExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(SpecialExam::class, 'special_exam_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<ClassArm, $this>
     */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    /**
     * @return HasMany<SpecialExamScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(SpecialExamScore::class);
    }
}
