<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar for the school app, grouped by module.
 *
 * Modules that are not built yet have no route; they are listed with a "Soon"
 * badge so the shape of the product is visible while it is being built.
 * Parents and students get a short portal menu instead.
 */
final class SchoolNavigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, icon: string, route: ?string, active: bool}>}>
     */
    public static function for(User $user): array
    {
        if (! $user->isStaffInCurrentSchool() && ! $user->is_platform_admin) {
            return self::build(self::portalGroups($user), $user);
        }

        $groups = [
            'Overview' => [
                ['Dashboard', 'layout-dashboard', 'school.dashboard', null, 'school.dashboard'],
                ['Notice board', 'megaphone', 'school.announcements.index', null, 'school.announcements.*'],
            ],
            'Academics' => [
                ['Sessions & Terms', 'calendar-range', 'school.sessions.index', 'academics.manage', 'school.sessions.*'],
                ['Classes', 'layers', 'school.classes.index', 'students.view', 'school.classes.*'],
                ['Subjects', 'book-open', 'school.subjects.index', 'academics.manage', 'school.subjects.*'],
                ['Promotion', 'trending-up', 'school.promotion.index', 'academics.manage', 'school.promotion.*'],
                ['Timetable', 'calendar-days', 'school.timetable.index', null, 'school.timetable.*'],
                ['Exam timetable', 'calendar-clock', 'school.exams.index', null, 'school.exams.*'],
                ['Attendance', 'clipboard-check', 'school.attendance.index', 'attendance.take', 'school.attendance.*'],
            ],
            'People' => [
                ['Students', 'graduation-cap', 'school.students.index', 'students.view', 'school.students.*'],
                ['Staff', 'briefcase', 'school.staff.index', 'staff.view', 'school.staff.*'],
                ['Admissions', 'user-plus', 'school.admissions.index', 'admissions.manage', 'school.admissions.*'],
                ['ID cards', 'id-card', 'school.id-cards.index', 'students.manage', 'school.id-cards.*'],
            ],
            'Results' => [
                ['Score entry', 'file-pen-line', 'school.scores.index', 'scores.enter', 'school.scores.*'],
                ['Results & broadsheets', 'file-text', 'school.results.index', 'results.view', 'school.results.*'],
                ['Classwork & CBT', 'monitor-check', 'school.classwork.index', 'scores.enter', 'school.classwork.*'],
            ],
            'Finance' => [
                ['Invoices', 'receipt', 'school.invoices.index', 'finance.view', 'school.invoices.*'],
                ['Payments', 'banknote-arrow-down', 'school.payments.index', 'finance.view', 'school.payments.*'],
                ['Debtors', 'hand-coins', 'school.debtors.index', 'finance.view', 'school.debtors.*'],
                ['Fee setup', 'settings-2', 'school.fees.setup', 'fees.manage', 'school.fees.*'],
                ['Expenses', 'trending-down', 'school.expenses.index', 'finance.view', 'school.expenses.*'],
                ['Cashflow', 'wallet', 'school.cashflow.index', 'finance.view', 'school.cashflow.*'],
                ['Payroll', 'banknote', 'school.payroll.index', 'payroll.manage', 'school.payroll.*'],
                ['Inventory', 'package', 'school.inventory.index', 'inventory.manage', 'school.inventory.*'],
            ],
            'System' => [
                ['School information', 'school', 'school.profile.edit', 'school.manage', 'school.profile.*'],
                ['Reports', 'bar-chart-3', 'school.reports.index', 'school.manage', 'school.reports.*'],

                ['Users & roles', 'users-round', 'school.members.index', 'users.manage', 'school.members.*'],
                ['Activity log', 'history', 'school.activity.index', 'activity.view', 'school.activity.*'],
                ['Settings', 'settings', 'school.settings.edit', 'school.manage', 'school.settings.*'],
            ],

        ];

        // A teacher who is also a parent at the same school.
        if ($user->guardianRecord()) {
            $groups['Overview'][] = ['My children', 'baby', 'school.portal', null, 'school.portal*'];
        }

        return self::build($groups, $user);
    }

    /**
     * @return array<string, list<array{0: string, 1: string, 2: ?string, 3: ?string, 4: ?string}>>
     */
    private static function portalGroups(User $user): array
    {
        if ($user->guardianRecord()) {
            return ['Family' => [
                ['My children', 'users', 'school.portal', null, 'school.portal*'],
                ['Notice board', 'megaphone', 'school.announcements.index', null, 'school.announcements.*'],
            ]];
        }

        return [
            'My school' => [
                ['My day', 'sun', 'school.portal', null, 'school.portal'],
                ['My work', 'notebook-pen', 'school.portal.classwork', null, 'school.portal.classwork'],
                ['Notice board', 'megaphone', 'school.announcements.index', null, 'school.announcements.*'],
            ],
        ];
    }

    /**
     * @param  array<string, list<array{0: string, 1: string, 2: ?string, 3: ?string, 4: ?string}>>  $groups
     * @return list<array{label: string, items: list<array{label: string, icon: string, route: ?string, active: bool}>}>
     */
    private static function build(array $groups, User $user): array
    {
        $navigation = [];

        foreach ($groups as $label => $items) {
            $visible = array_values(array_filter(
                $items,
                fn (array $item): bool => $item[3] === null || $user->can($item[3]),
            ));

            if ($visible === []) {
                continue;
            }

            $navigation[] = [
                'label' => $label,
                'items' => array_map(fn (array $item): array => [
                    'label' => $item[0],
                    'icon' => $item[1],
                    'route' => $item[2],
                    'active' => $item[4] !== null && Route::is($item[4]),
                ], $visible),
            ];
        }

        return $navigation;
    }
}
