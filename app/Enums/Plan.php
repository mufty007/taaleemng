<?php

namespace App\Enums;

use App\Support\Money;

/**
 * What a school pays Ta'aleem for. Every plan includes all features; the
 * difference is seat limits (students and staff) and the listed price.
 * Prices and caps live in config/taaleem.php so they can change without a deploy.
 */
enum Plan: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Premium = 'premium';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Starter',
            self::Standard => 'Growth',
            self::Premium => 'Campus',
            self::Custom => 'Custom',
        };
    }

    /**
     * Flat fee for a term, in kobo, or null when not set / custom.
     */
    public function flatPriceTermly(): ?int
    {
        $naira = config("taaleem.plans.{$this->value}.flat_termly");

        return filled($naira) ? Money::toKobo((string) $naira) : null;
    }

    /**
     * Flat fee for a year (session), in kobo, or null when not set / custom.
     */
    public function flatPriceAnnual(): ?int
    {
        $naira = config("taaleem.plans.{$this->value}.flat_annual");

        return filled($naira) ? Money::toKobo((string) $naira) : null;
    }

    /**
     * The listed price per student per term, in kobo, or null when not set.
     */
    public function defaultPrice(): ?int
    {
        $naira = config("taaleem.plans.{$this->value}.per_student");
        if (! filled($naira)) {
            $naira = config("taaleem.marketing.prices.{$this->value}");
        }

        return filled($naira) ? Money::toKobo((string) $naira) : null;
    }

    public function maxStudents(): ?int
    {
        $value = config("taaleem.plans.{$this->value}.max_students");

        return is_numeric($value) ? (int) $value : null;
    }

    public function maxStaff(): ?int
    {
        $value = config("taaleem.plans.{$this->value}.max_staff");

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Modules this plan leaves off. All public tiers include everything;
     * platform admins can still switch modules off by hand.
     *
     * @return list<string>
     */
    public function disabledModules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $plan) => [$plan->value => $plan->label()])->all();
    }

    /**
     * The three public tiers schools can pick (excludes Custom).
     *
     * @return list<self>
     */
    public static function publicTiers(): array
    {
        return [self::Basic, self::Standard, self::Premium];
    }
}
