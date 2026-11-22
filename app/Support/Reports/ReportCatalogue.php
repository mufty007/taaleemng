<?php

namespace App\Support\Reports;

use App\Models\User;

/**
 * Every report on the Reports page: what it covers, how it is filtered, where it sits,
 * and the permission that opens it.
 *
 * Each report needs the permission that already governs the same data elsewhere in the
 * app, so principals and head teachers get the academic and people reports, the bursar
 * the money reports and the store keeper the stores report. School admins get them all.
 *
 * @phpstan-type Report array{key: string, title: string, body: string, icon: string, period: 'term'|'session'|null, permission: string, covers: list<string>}
 */
final class ReportCatalogue
{
    /**
     * Reports grouped the way the Reports page lists them.
     *
     * @return array<string, list<Report>>
     */
    public static function groups(): array
    {
        return [
            'Students & academics' => [
                [
                    'key' => 'enrolment',
                    'title' => 'Enrolment & demographics',
                    'body' => 'Who is on roll this session, class by class.',
                    'icon' => 'graduation-cap',
                    'period' => 'session',
                    'permission' => 'academics.manage',
                    'covers' => ['Boys and girls per class and section', 'New intake against returning students', 'Class capacity, ages, religion and state of origin', 'Enrolment across sessions'],
                ],
                [
                    'key' => 'academic',
                    'title' => 'Academic performance',
                    'body' => 'How each class, subject and teacher is doing this term.',
                    'icon' => 'file-text',
                    'period' => 'term',
                    'permission' => 'results.approve',
                    'covers' => ['Class averages and pass rates', 'Strongest and weakest subjects', 'Grade spread and top students', 'Students below the pass mark and score-entry progress'],
                ],
                [
                    'key' => 'attendance',
                    'title' => 'Attendance',
                    'body' => 'Presence rates for students and staff across the term.',
                    'icon' => 'clipboard-check',
                    'period' => 'term',
                    'permission' => 'attendance.view',
                    'covers' => ['Rates, absences and lateness by class', 'Week-by-week trend and day-of-week pattern', 'Chronic absentees', 'Staff presence'],
                ],
                [
                    'key' => 'admissions',
                    'title' => 'Admissions',
                    'body' => 'Applications for the session, from form to first day.',
                    'icon' => 'user-plus',
                    'period' => 'session',
                    'permission' => 'admissions.manage',
                    'covers' => ['Applied, examined, offered and enrolled', 'Demand by class and by month', 'Entrance exam score spread', 'Online against walk-in applications'],
                ],
            ],
            'Money' => [
                [
                    'key' => 'fees',
                    'title' => 'Fees & collections',
                    'body' => 'What was billed, what came in and who still owes.',
                    'icon' => 'hand-coins',
                    'period' => 'term',
                    'permission' => 'fees.manage',
                    'covers' => ['Collection rate by class and fee item', 'Debt ageing across all terms', 'Week-by-week collections and payment methods', 'Who received payments, and the largest balances'],
                ],
                [
                    'key' => 'finance',
                    'title' => 'Income & expenditure',
                    'body' => 'Fees in against spending out, term on term.',
                    'icon' => 'wallet',
                    'period' => 'term',
                    'permission' => 'expenses.manage',
                    'covers' => ['Month-by-month statement', 'Spending by category and the largest items', 'Payroll runs for the period', 'Comparison with earlier terms'],
                ],
                [
                    'key' => 'inventory',
                    'title' => 'Stores & inventory',
                    'body' => 'Stock on hand, its value, and what moved this term.',
                    'icon' => 'package',
                    'period' => 'term',
                    'permission' => 'inventory.manage',
                    'covers' => ['Stock value by category', 'Items at or below reorder level', 'Received, issued and sold in the term', 'Fastest-moving items'],
                ],
            ],
            'Staff' => [
                [
                    'key' => 'staff',
                    'title' => 'Staff & workload',
                    'body' => 'Headcount, wage bill and who teaches what.',
                    'icon' => 'briefcase',
                    'period' => null,
                    'permission' => 'staff.view',
                    'covers' => ['Headcount by department, gender and contract', 'Monthly wage bill (for those who run payroll)', 'Teaching load and periods per week', 'Subjects with no teacher, length of service and qualifications'],
                ],
            ],
        ];
    }

    /**
     * The reports a user may open, in the same groups. Groups with none are left out.
     *
     * @return array<string, list<Report>>
     */
    public static function visibleTo(User $user): array
    {
        return array_filter(array_map(
            fn (array $reports): array => array_values(array_filter($reports, fn (array $report): bool => $user->can($report['permission']))),
            self::groups(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(array_merge(...array_values(self::groups())), 'key');
    }

    /**
     * @return Report|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::groups() as $reports) {
            foreach ($reports as $report) {
                if ($report['key'] === $key) {
                    return $report;
                }
            }
        }

        return null;
    }
}
