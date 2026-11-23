<?php

namespace App\Support\Schools;

use App\Enums\StaffStatus;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\School;
use App\Models\Staff;

/**
 * The things a new school does before Ta'aleem is useful. Schools can hide or
 * rename steps in Settings; the dashboard and platform console both use this.
 */
final class SetupChecklist
{
    /**
     * @return array<string, array{title: string, body: string, icon: string, route: string}>
     */
    public static function defaults(): array
    {
        return [
            'profile' => ['title' => 'Add your school details', 'body' => 'Logo, motto, address and phone for report cards, receipts and ID cards.', 'icon' => 'school', 'route' => 'school.profile.edit'],
            'session' => ['title' => 'Set up the session and terms', 'body' => 'Create the session with First, Second and Third Term dates.', 'icon' => 'calendar-range', 'route' => 'school.sessions.index'],
            'classes' => ['title' => 'Create classes and subjects', 'body' => 'Nursery to SS 3, arms like JSS 1A, and the subjects each class takes.', 'icon' => 'layers', 'route' => 'school.classes.index'],
            'students' => ['title' => 'Bring in your students', 'body' => 'Enrol students with their parents’ contact details.', 'icon' => 'graduation-cap', 'route' => 'school.students.index'],
            'staff' => ['title' => 'Add your staff', 'body' => 'Give the principal, bursar and teachers their own logins.', 'icon' => 'user-plus', 'route' => 'school.staff.index'],
        ];
    }

    /**
     * @return list<array{key: string, title: string, body: string, icon: string, done: bool, route: string}>
     */
    public static function steps(School $school, ?string $address, ?string $phone, ?AcademicSession $session, int $classCount, int $studentCount, int $staffCount): array
    {
        $custom = $school->setup_checklist ?? [];
        $done = [
            'profile' => filled($address) && filled($phone),
            'session' => $session !== null,
            'classes' => $classCount > 0,
            'students' => $studentCount > 0,
            'staff' => $staffCount > 1,
        ];

        $steps = [];

        foreach (self::defaults() as $key => $default) {
            $override = is_array($custom[$key] ?? null) ? $custom[$key] : [];

            if (($override['enabled'] ?? true) === false) {
                continue;
            }

            $steps[] = [
                'key' => $key,
                'title' => (string) ($override['title'] ?? $default['title']),
                'body' => (string) ($override['body'] ?? $default['body']),
                'icon' => $default['icon'],
                'done' => $done[$key] ?? false,
                'route' => $default['route'],
            ];
        }

        return $steps;
    }

    /**
     * @return list<array{key: string, title: string, body: string, icon: string, done: bool, route: string}>
     */
    public static function for(School $school): array
    {
        $session = AcademicSession::current();

        return self::steps(
            $school,
            $school->address,
            $school->phone,
            $session,
            ClassArm::query()->count(),
            Enrolment::query()->where('academic_session_id', $session?->id)->count(),
            Staff::query()->where('status', StaffStatus::Active)->count(),
        );
    }
}
