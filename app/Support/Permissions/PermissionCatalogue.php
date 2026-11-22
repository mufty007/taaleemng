<?php

namespace App\Support\Permissions;

use App\Enums\SchoolRole;

/**
 * Every permission in the app, grouped by module, and the defaults for each role template.
 *
 * Permissions are global; roles are per school (Spatie teams mode, keyed by school_id).
 * Teachers are additionally narrowed to their assigned classes and subjects by policies.
 */
final class PermissionCatalogue
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'School' => [
                'school.manage' => 'Edit the school profile and settings',
                'users.manage' => 'Invite users and assign roles',
                'activity.view' => 'View the activity log',
            ],
            'Academics' => [
                'academics.manage' => 'Manage sessions, terms, classes and subjects',
                'timetable.manage' => 'Build and publish timetables',
                'attendance.take' => 'Mark attendance for assigned classes',
                'attendance.view' => 'View attendance reports for the school',
            ],
            'People' => [
                'admissions.manage' => 'Manage applications and enrolment',
                'students.view' => 'View students in the classes they teach',
                'students.view-all' => 'View every student in the school',
                'students.manage' => 'Add and edit students and guardians',
                'staff.view' => 'View staff records',
                'staff.manage' => 'Add and edit staff records',
            ],
            'Results' => [
                'scores.enter' => 'Enter scores for assigned subjects',
                'results.view' => 'View results and broadsheets',
                'results.approve' => 'Approve submitted results',
                'results.publish' => 'Publish results to parents and students',
            ],
            'Exams & CBT' => [
                'cbt.manage' => 'Set questions and schedule CBT exams',
                'cbt.take' => 'Write CBT exams',
            ],
            'Finance' => [
                'fees.manage' => 'Set fees and generate invoices',
                'payments.record' => 'Record and confirm payments',
                'finance.view' => 'View collections, debtors and cashflow',
                'expenses.manage' => 'Record and approve expenses',
                'payroll.manage' => 'Run payroll and issue payslips',
            ],
            'Operations' => [
                'inventory.manage' => 'Manage stores and stock',
            ],
            'Portal' => [
                'portal.wards' => 'See own wards’ results, fees and attendance',
                'portal.self' => 'See own results, timetable and invoices',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::groups())));
    }

    /**
     * @return list<string>
     */
    public static function forRole(SchoolRole $role): array
    {
        return match ($role) {
            SchoolRole::SchoolAdmin => array_values(array_filter(
                self::all(),
                fn (string $permission): bool => ! str_starts_with($permission, 'portal.') && $permission !== 'cbt.take',
            )),
            SchoolRole::Principal => [
                'academics.manage', 'timetable.manage', 'attendance.take', 'attendance.view',
                'admissions.manage', 'students.view', 'students.view-all', 'students.manage', 'staff.view',
                'scores.enter', 'results.view', 'results.approve', 'results.publish',
                'cbt.manage', 'finance.view', 'activity.view',
            ],
            SchoolRole::HeadTeacher => [
                'academics.manage', 'timetable.manage', 'attendance.take', 'attendance.view',
                'admissions.manage', 'students.view', 'students.view-all',
                'scores.enter', 'results.view', 'results.approve', 'cbt.manage',
            ],
            SchoolRole::Bursar => [
                'students.view', 'students.view-all', 'fees.manage', 'payments.record', 'finance.view',
                'expenses.manage', 'payroll.manage',
            ],
            SchoolRole::Teacher => [
                'attendance.take', 'students.view', 'scores.enter', 'results.view', 'cbt.manage',
            ],
            SchoolRole::StoreKeeper => ['inventory.manage', 'students.view', 'students.view-all'],
            SchoolRole::Student => ['portal.self', 'cbt.take'],
            SchoolRole::Parent => ['portal.wards'],
        };
    }
}
