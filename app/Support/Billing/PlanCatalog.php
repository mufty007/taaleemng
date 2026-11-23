<?php

namespace App\Support\Billing;

use App\Enums\BillingModel;
use App\Enums\Plan;
use App\Support\Money;

/**
 * Readable plan cards for marketing, signup and the platform billing UI.
 */
final class PlanCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     popular: bool,
     *     max_students: ?int,
     *     max_staff: ?int,
     *     flat_termly: ?string,
     *     flat_annual: ?string,
     *     per_student: ?string,
     *     description: string,
     *     features: list<string>
     * }>
     */
    public static function publicPlans(): array
    {
        return collect(Plan::publicTiers())->map(function (Plan $plan): array {
            return [
                'key' => $plan->value,
                'name' => $plan->label(),
                'popular' => $plan === Plan::Standard,
                'max_students' => $plan->maxStudents(),
                'max_staff' => $plan->maxStaff(),
                'flat_termly' => self::formatKobo($plan->flatPriceTermly()),
                'flat_annual' => self::formatKobo($plan->flatPriceAnnual()),
                'per_student' => self::formatKobo($plan->defaultPrice()),
                'description' => (string) config("taaleem.plans.{$plan->value}.description", ''),
                'features' => array_values((array) config("taaleem.plans.{$plan->value}.features", [])),
            ];
        })->all();
    }

    /**
     * Amount in kobo for an invoice, given the school's billing choice.
     */
    public static function invoiceAmount(Plan $plan, BillingModel $model, string $cycle, int $students, ?int $overridePerStudent = null): ?int
    {
        if ($model === BillingModel::Flat) {
            return $cycle === 'annual' ? $plan->flatPriceAnnual() : $plan->flatPriceTermly();
        }

        $unit = $overridePerStudent ?? $plan->defaultPrice();

        return $unit === null ? null : $unit * max(0, $students);
    }

    private static function formatKobo(?int $kobo): ?string
    {
        return $kobo === null ? null : Money::format($kobo);
    }
}
