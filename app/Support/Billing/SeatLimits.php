<?php

namespace App\Support\Billing;

use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Support\Tenancy\CurrentSchool;

/**
 * How many students and staff a school may have on its current plan.
 * Custom / null caps mean unlimited.
 */
final class SeatLimits
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function maxStudents(?School $school = null): ?int
    {
        $school ??= $this->currentSchool->getOrFail();

        return $school->max_students ?? $school->plan->maxStudents();
    }

    public function maxStaff(?School $school = null): ?int
    {
        $school ??= $this->currentSchool->getOrFail();

        return $school->max_staff ?? $school->plan->maxStaff();
    }

    public function studentCount(?School $school = null): int
    {
        $school ??= $this->currentSchool->getOrFail();

        return $this->currentSchool->run($school, fn () => Student::query()->where('status', StudentStatus::Active)->count());
    }

    public function staffCount(?School $school = null): int
    {
        $school ??= $this->currentSchool->getOrFail();

        return $this->currentSchool->run($school, fn () => Staff::query()->where('status', StaffStatus::Active)->count());
    }

    public function canAddStudent(?School $school = null): bool
    {
        $max = $this->maxStudents($school);

        return $max === null || $this->studentCount($school) < $max;
    }

    public function canAddStaff(?School $school = null): bool
    {
        $max = $this->maxStaff($school);

        return $max === null || $this->staffCount($school) < $max;
    }

    /**
     * @return array{kind: 'students'|'staff', used: int, max: int, plan: string}
     */
    public function studentLimitSnapshot(?School $school = null): array
    {
        $school ??= $this->currentSchool->getOrFail();

        return [
            'kind' => 'students',
            'used' => $this->studentCount($school),
            'max' => (int) $this->maxStudents($school),
            'plan' => $school->plan->label(),
        ];
    }

    /**
     * @return array{kind: 'students'|'staff', used: int, max: int, plan: string}
     */
    public function staffLimitSnapshot(?School $school = null): array
    {
        $school ??= $this->currentSchool->getOrFail();

        return [
            'kind' => 'staff',
            'used' => $this->staffCount($school),
            'max' => (int) $this->maxStaff($school),
            'plan' => $school->plan->label(),
        ];
    }
}
