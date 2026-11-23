<?php

namespace App\Support\Platform;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Parts of Ta'aleem a super admin can switch on or off per school (by plan, or by hand).
 * Each module lists the route names it owns; a switched-off module's pages answer 403
 * and its menu items disappear.
 */
final class Modules
{
    /** @var array<string, array{label: string, description: string, routes: list<string>}> */
    public const ALL = [
        'chat' => ['label' => 'Staff chat', 'description' => 'Channels and direct messages for staff.', 'routes' => ['school.chat*']],
        'classwork' => ['label' => 'Classwork & CBT', 'description' => 'Assignments, quizzes and computer-based tests.', 'routes' => ['school.classwork*', 'school.portal.classwork', 'school.portal.assignments*', 'school.portal.quizzes*']],
        'special-exams' => ['label' => 'Special exams', 'description' => 'WAEC, NECO, JAMB and BECE practice exams.', 'routes' => ['school.special-exams*']],
        'admissions' => ['label' => 'Admissions', 'description' => 'Online applications and entrance exams.', 'routes' => ['school.admissions*']],
        'inventory' => ['label' => 'Inventory', 'description' => 'Store, uniforms and books.', 'routes' => ['school.inventory*']],
        'payroll' => ['label' => 'Payroll', 'description' => 'Monthly payroll and payslips.', 'routes' => ['school.payroll*']],
        'online-payments' => ['label' => 'Online fee payment', 'description' => 'Parents pay fees by card, transfer or USSD.', 'routes' => ['school.portal.invoices.pay', 'school.portal.children.pay']],
    ];

    /**
     * The switched-off module whose page this request is for, if any.
     */
    public static function blocking(School $school, Request $request): ?string
    {
        foreach ($school->disabled_modules ?? [] as $key) {
            if (isset(self::ALL[$key]) && $request->routeIs(...self::ALL[$key]['routes'])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Whether a route name belongs to a module the school has switched off.
     */
    public static function hidesRoute(School $school, string $route): bool
    {
        foreach ($school->disabled_modules ?? [] as $key) {
            foreach (self::ALL[$key]['routes'] ?? [] as $pattern) {
                if (Str::is($pattern, $route)) {
                    return true;
                }
            }
        }

        return false;
    }
}
