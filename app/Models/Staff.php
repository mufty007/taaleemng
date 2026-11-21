<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\StaffStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An employee record in one school. A person who works at two schools has a staff record in each.
 *
 * @property Gender $gender
 * @property StaffStatus $status
 * @property EmploymentType $employment_type
 * @property Carbon|null $date_employed
 */
#[Fillable([
    'user_id', 'staff_no', 'title', 'first_name', 'last_name', 'other_names', 'gender', 'phone',
    'email', 'designation', 'department', 'employment_type', 'date_employed', 'qualification', 'status',
])]
class Staff extends Model
{
    use BelongsToSchool, HasUlids, SoftDeletes;

    protected $table = 'staff';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StaffStatus::class,
            'employment_type' => EmploymentType::class,
            'date_employed' => 'date',
        ];
    }

    /**
     * @return array<int, string>
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'teacher_id');
    }

    /**
     * @return HasMany<ClassArm, $this>
     */
    public function formClasses(): HasMany
    {
        return $this->hasMany(ClassArm::class, 'form_teacher_id');
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->title, $this->first_name, $this->last_name])));
    }

    /**
     * Class arm ids this person teaches a subject in or is form teacher of.
     *
     * @return list<int>
     */
    public function classArmIds(): array
    {
        return ClassSubject::query()->where('teacher_id', $this->id)->pluck('class_arm_id')
            ->merge(ClassArm::query()->where('form_teacher_id', $this->id)->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }
}
