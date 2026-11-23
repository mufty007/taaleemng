<?php

namespace App\Enums;

use App\Support\Money;

/**
 * What a school pays Ta'aleem for. Prices are per student per term (config/taaleem.php);
 * each plan switches some modules off by default.
 */
enum Plan: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Premium = 'premium';
    case Custom = 'custom';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The listed price per student per term, in kobo, or null when not set.
     */
    public function defaultPrice(): ?int
    {
        $naira = config("taaleem.marketing.prices.{$this->value}");

        return filled($naira) ? Money::toKobo((string) $naira) : null;
    }

    /**
     * Modules this plan leaves off.
     *
     * @return list<string>
     */
    public function disabledModules(): array
    {
        return match ($this) {
            self::Basic => ['chat', 'inventory', 'payroll', 'special-exams'],
            self::Standard => ['payroll'],
            self::Premium, self::Custom => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $plan) => [$plan->value => $plan->label()])->all();
    }
}
