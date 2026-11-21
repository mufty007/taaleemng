<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\StaffStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An employee record in one school. A person who works at two schools has a staff record in each.
 *
 * @property Gender $gender
 * @property StaffStatus $status
 * @property EmploymentType $employment_type
 * @property Carbon|null $date_employed
 * @property int|null $basic_salary
 * @property int $allowances
 * @property int $deductions
 */
#[Fillable([
    'user_id', 'staff_no', 'title', 'first_name', 'last_name', 'other_names', 'gender', 'phone',
    'email', 'designation', 'department', 'employment_type', 'date_employed', 'qualification', 'photo_path', 'status',
    'basic_salary', 'allowances', 'deductions', 'bank_name', 'bank_account_name', 'bank_account_number',
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
            'basic_salary' => 'integer',
            'allowances' => 'integer',
            'deductions' => 'integer',
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

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /**
     * Currently employed (not on leave / exited).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', StaffStatus::Active);
    }

    /**
     * Have a basic salary on file for payroll.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withSalary(Builder $query): void
    {
        $query->whereNotNull('basic_salary')->where('basic_salary', '>', 0);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->title, $this->first_name, $this->last_name])));
    }

    /**
     * Net monthly pay from the salary fields on file (kobo).
     */
    public function netMonthlyPay(): ?int
    {
        if ($this->basic_salary === null || $this->basic_salary <= 0) {
            return null;
        }

        return max(0, (int) $this->basic_salary + (int) $this->allowances - (int) $this->deductions);
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1));
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('school.photos.staff', [$this, 'v' => substr(md5($this->photo_path), 0, 8)]) : null;
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
