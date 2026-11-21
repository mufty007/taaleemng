<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * Who may see a student's record:
 * - staff with students.view-all (admin, principal, bursar, store keeper),
 * - teachers, but only for students in a class they teach or are form teacher of,
 * - the student's own guardians, and the student themself.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->can('students.view-all')) {
            return true;
        }

        if ($student->user_id !== null && $student->user_id === $user->id) {
            return true;
        }

        $guardian = $user->guardianRecord();

        if ($guardian && $guardian->students()->whereKey($student->id)->exists()) {
            return true;
        }

        $staff = $user->staffRecord();

        if ($staff && $user->can('students.view')) {
            return Student::query()
                ->whereKey($student->id)
                ->inClassArms($staff->classArmIds())
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('students.manage');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.manage');
    }
}
