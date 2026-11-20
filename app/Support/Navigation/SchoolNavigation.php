<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar for the school app, grouped by module.
 *
 * Modules that are not built yet have no route; they are listed with a "Soon"
 * badge so the shape of the product is visible while it is being built.
 */
final class SchoolNavigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, icon: string, route: ?string, active: bool}>}>
     */
    public static function for(User $user): array
    {
        $groups = [
            'Overview' => [
                ['Dashboard', 'layout-dashboard', 'school.dashboard', null],
            ],
            'Academics' => [
                ['Sessions & Terms', 'calendar-range', null, 'academics.manage'],
                ['Classes & Subjects', 'layers', null, 'academics.manage'],
                ['Time Table', 'calendar-days', null, null],
                ['Attendance', 'clipboard-check', null, 'attendance.take'],
            ],
            'People' => [
                ['Admissions', 'user-plus', null, 'admissions.manage'],
                ['Students', 'graduation-cap', null, 'students.view'],
                ['Staff', 'briefcase', null, 'staff.view'],
            ],
            'Results' => [
                ['Score entry', 'file-pen-line', null, 'scores.enter'],
                ['Report cards', 'file-text', null, 'results.view'],
                ['Exams & CBT', 'monitor-check', null, 'cbt.manage'],
            ],
            'Finance' => [
                ['School fees', 'receipt', null, 'fees.manage'],
                ['Cashflow', 'wallet', null, 'finance.view'],
                ['Payroll', 'banknote', null, 'payroll.manage'],
                ['Inventory', 'package', null, 'inventory.manage'],
            ],
            'System' => [
                ['School information', 'school', null, 'school.manage'],
                ['Users & roles', 'users-round', null, 'users.manage'],
                ['Activity log', 'history', null, 'activity.view'],
                ['Settings', 'settings', null, 'school.manage'],
            ],
        ];

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
                    'active' => $item[2] !== null && Route::is($item[2].'*'),
                ], $visible),
            ];
        }

        return $navigation;
    }
}
