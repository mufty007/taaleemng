<?php

namespace App\Support\Classwork;

use App\Enums\ClassworkStatus;
use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who may set and mark classwork (the subject's teacher, or academic staff),
 * and which students it is for (the class this session).
 */
final class ClassworkAccess
{
    public static function canManage(User $user, ClassSubject $classSubject): bool
    {
        return $user->can('academics.manage')
            || ($classSubject->teacher_id !== null && $classSubject->teacher_id === $user->staffRecord()?->id);
    }

    /**
     * Class subjects the user can set work for, with their class and subject loaded.
     *
     * @return Collection<int, ClassSubject>
     */
    public static function manageable(User $user): Collection
    {
        return ClassSubject::query()
            ->with(['subject', 'classArm.level'])
            ->when(! $user->can('academics.manage'), fn (Builder $query) => $query->where('teacher_id', $user->staffRecord()->id ?? 0))
            ->get()
            ->sortBy(fn (ClassSubject $classSubject) => sprintf('%03d-%s-%s', $classSubject->classArm->level->position, $classSubject->classArm->name, $classSubject->subject->name))
            ->values();
    }

    /**
     * The class this session that a student belongs to, if any.
     */
    public static function classArmIdOf(Student $student): ?int
    {
        $sessionId = AcademicSession::current()->id ?? 0;

        return Enrolment::query()->where('student_id', $student->id)->where('academic_session_id', $sessionId)->value('class_arm_id');
    }

    /**
     * The teacher, the student it is set for, or one of that student's parents.
     */
    public static function canSeeAssignment(User $user, Assignment $assignment): bool
    {
        if (self::canManage($user, $assignment->classSubject)) {
            return true;
        }

        if ($assignment->status !== ClassworkStatus::Published) {
            return false;
        }

        $students = collect([$user->studentRecord()])->merge($user->guardianRecord()->students ?? [])->filter();

        return $students->contains(fn (Student $student) => self::isFor($student, $assignment->classSubject));
    }

    /**
     * The teacher, the student who handed it in, or one of their parents.
     */
    public static function canSeeSubmission(User $user, AssignmentSubmission $submission): bool
    {
        return self::canManage($user, $submission->assignment->classSubject)
            || $user->studentRecord()?->id === $submission->student_id
            || (bool) $user->guardianRecord()?->students()->whereKey($submission->student_id)->exists();
    }

    public static function isFor(Student $student, ClassSubject $classSubject): bool
    {
        return self::classArmIdOf($student) === $classSubject->class_arm_id;
    }
}
