<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subject taught in one class arm, and who teaches it.
 */
#[Fillable(['class_arm_id', 'subject_id', 'teacher_id', 'is_compulsory'])]
class ClassSubject extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_compulsory' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ClassArm, $this>
     */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
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
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }
}
