<?php

namespace App\Support\Billing;

use App\Enums\BillingModel;
use App\Enums\BroadcastAudience;
use App\Enums\BroadcastLevel;
use App\Enums\Plan;
use App\Enums\SchoolRole;
use App\Models\PlatformBroadcast;
use App\Models\User;
use App\Notifications\PlatformBroadcastNotification;
use App\Support\Money;
use App\Support\Platform\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Readable plan cards for marketing, signup and the platform billing UI.
 * Super admins can edit names, seats and prices; saved values live in
 * platform settings and overlay config/taaleem.php. Invoice amounts are fixed
 * when an invoice is created, so a catalog change only affects the next bill.
 */
final class PlanCatalog
{
    public const SETTING_KEY = 'subscription_plans';

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
     * Editable form values for every plan (including Custom), in Naira strings.
     *
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     max_students: ?int,
     *     max_staff: ?int,
     *     flat_termly: ?string,
     *     flat_annual: ?string,
     *     per_student: ?string,
     *     features: string
     * }>
     */
    public static function editable(): array
    {
        return collect(Plan::cases())->mapWithKeys(function (Plan $plan): array {
            $features = array_values((array) config("taaleem.plans.{$plan->value}.features", []));

            return [$plan->value => [
                'label' => $plan->label(),
                'description' => (string) config("taaleem.plans.{$plan->value}.description", ''),
                'max_students' => $plan->maxStudents(),
                'max_staff' => $plan->maxStaff(),
                'flat_termly' => self::nairaString(config("taaleem.plans.{$plan->value}.flat_termly")),
                'flat_annual' => self::nairaString(config("taaleem.plans.{$plan->value}.flat_annual")),
                'per_student' => self::nairaString(
                    config("taaleem.plans.{$plan->value}.per_student")
                        ?: config("taaleem.marketing.prices.{$plan->value}")
                ),
                'features' => implode("\n", $features),
            ]];
        })->all();
    }

    /**
     * Persist one plan’s edits into the catalog. Prices only affect invoices
     * created after this save; open invoices keep the amount they were issued with.
     *
     * @param  array<string, mixed>  $row
     */
    public static function saveOne(Plan $plan, array $row): void
    {
        $catalog = self::storedCatalog();

        foreach (Plan::cases() as $case) {
            if (! isset($catalog[$case->value])) {
                $catalog[$case->value] = self::snapshot($case);
            }
        }

        $catalog[$plan->value] = self::normaliseRow($plan, $row);

        PlatformSettings::put([self::SETTING_KEY => json_encode($catalog, JSON_THROW_ON_ERROR)]);
        self::applySaved();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function storedCatalog(): array
    {
        $raw = PlatformSettings::get(self::SETTING_KEY);

        if (! filled($raw)) {
            return [];
        }

        try {
            /** @var array<string, array<string, mixed>> $catalog */
            $catalog = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($catalog) ? $catalog : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(Plan $plan): array
    {
        return self::normaliseRow($plan, [
            'label' => $plan->label(),
            'description' => (string) config("taaleem.plans.{$plan->value}.description", ''),
            'max_students' => $plan->maxStudents(),
            'max_staff' => $plan->maxStaff(),
            'flat_termly' => config("taaleem.plans.{$plan->value}.flat_termly"),
            'flat_annual' => config("taaleem.plans.{$plan->value}.flat_annual"),
            'per_student' => config("taaleem.plans.{$plan->value}.per_student")
                ?: config("taaleem.marketing.prices.{$plan->value}"),
            'features' => implode("\n", array_values((array) config("taaleem.plans.{$plan->value}.features", []))),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function normaliseRow(Plan $plan, array $row): array
    {
        $features = filled($row['features'] ?? null)
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $row['features']) ?: [])))
            : [];

        return [
            'label' => trim((string) ($row['label'] ?? $plan->label())),
            'description' => trim((string) ($row['description'] ?? '')),
            'max_students' => filled($row['max_students'] ?? null) ? (int) $row['max_students'] : null,
            'max_staff' => filled($row['max_staff'] ?? null) ? (int) $row['max_staff'] : null,
            'flat_termly' => self::cleanAmount($row['flat_termly'] ?? null),
            'flat_annual' => self::cleanAmount($row['flat_annual'] ?? null),
            'per_student' => self::cleanAmount($row['per_student'] ?? null),
            'features' => $features,
        ];
    }

    /**
     * Overlay saved plan catalog onto config (called at boot and after save).
     */
    public static function applySaved(): void
    {
        $raw = PlatformSettings::get(self::SETTING_KEY);

        if (! filled($raw)) {
            return;
        }

        try {
            /** @var array<string, array<string, mixed>> $catalog */
            $catalog = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        foreach ($catalog as $key => $overrides) {
            if (! is_array($overrides) || Plan::tryFrom((string) $key) === null) {
                continue;
            }

            $merged = array_merge(config("taaleem.plans.{$key}", []), $overrides);
            config(["taaleem.plans.{$key}" => $merged]);

            if (array_key_exists('per_student', $overrides)) {
                config(["taaleem.marketing.prices.{$key}" => $overrides['per_student']]);
            }
        }
    }

    /**
     * Banner (and optional email) telling school admins that plans are changing.
     * New prices apply on their next invoice.
     */
    public static function notifySchools(User $actor, string $title, string $body, bool $emailAdmins = true): PlatformBroadcast
    {
        $broadcast = PlatformBroadcast::query()->create([
            'title' => $title,
            'body' => $body,
            'level' => BroadcastLevel::Warning,
            'audience' => BroadcastAudience::Admins,
            'school_ids' => null,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'email_admins' => $emailAdmins,
            'created_by' => $actor->id,
        ]);

        if ($emailAdmins) {
            $admins = User::query()
                ->whereNotNull('email')
                ->whereIn('id', DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', SchoolRole::SchoolAdmin->value)
                    ->select('model_has_roles.model_id'))
                ->get();

            Notification::send($admins, new PlatformBroadcastNotification($broadcast->title, $broadcast->body));
        }

        activity('platform')->performedOn($broadcast)->log("broadcast “{$broadcast->title}” about plan updates");

        return $broadcast;
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

    private static function nairaString(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Money::toNaira(Money::toKobo((string) $value));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function cleanAmount(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Money::toNaira(Money::toKobo((string) $value));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
