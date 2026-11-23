<?php

namespace App\Support\Schools;

use App\Enums\StaffStatus;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\School;
use App\Models\Staff;

/**
 * The five things a new school does before Ta'aleem is useful: shown on the school
 * dashboard until done, and on the school's page in the platform console.
 */
final class SetupChecklist
{
    /**
     * @return list<array{title: string, body: string, icon: string, done: bool, route: string}>
     */
    public static function steps(?string $address, ?string $phone, ?AcademicSession $session, int $classCount, int $studentCount, int $staffCount): array
    {
        return [
            ['title' => 'Add your school details', 'body' => 'Logo, motto, address and phone for report cards, receipts and ID cards.', 'icon' => 'school', 'done' => filled($address) && filled($phone), 'route' => 'school.profile.edit'],
            ['title' => 'Set up the session and terms', 'body' => 'Create the session with First, Second and Third Term dates.', 'icon' => 'calendar-range', 'done' => $session !== null, 'route' => 'school.sessions.index'],
            ['title' => 'Create classes and subjects', 'body' => 'Nursery to SS 3, arms like JSS 1A, and the subjects each class takes.', 'icon' => 'layers', 'done' => $classCount > 0, 'route' => 'school.classes.index'],
            ['title' => 'Bring in your students', 'body' => 'Enrol students with their parents’ contact details.', 'icon' => 'graduation-cap', 'done' => $studentCount > 0, 'route' => 'school.students.index'],
            ['title' => 'Add your staff', 'body' => 'Give the principal, bursar and teachers their own logins.', 'icon' => 'user-plus', 'done' => $staffCount > 1, 'route' => 'school.staff.index'],
        ];
    }

    /**
     * The steps for a school, counted inside its own context (call within CurrentSchool::run()).
     *
     * @return list<array{title: string, body: string, icon: string, done: bool, route: string}>
     */
    public static function for(School $school): array
    {
        $session = AcademicSession::current();

        return self::steps(
            $school->address,
            $school->phone,
            $session,
            ClassArm::query()->count(),
            Enrolment::query()->where('academic_session_id', $session?->id)->count(),
            Staff::query()->where('status', StaffStatus::Active)->count(),
        );
    }
}
