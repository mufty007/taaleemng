<?php

namespace App\Enums;

/**
 * The exams a school runs outside termly results.
 */
enum SpecialExamKind: string
{
    case Mock = 'mock';
    case Waec = 'waec';
    case Neco = 'neco';
    case Jamb = 'jamb';
    case Bece = 'bece';
    case CommonEntrance = 'common_entrance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mock => 'School mock',
            self::Waec => 'WAEC practice',
            self::Neco => 'NECO practice',
            self::Jamb => 'JAMB (UTME) practice',
            self::Bece => 'BECE practice',
            self::CommonEntrance => 'Common Entrance practice',
            self::Other => 'Other exam',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Mock => 'brand',
            self::Waec, self::Neco => 'naija',
            self::Jamb => 'sky',
            self::Bece, self::CommonEntrance => 'gold',
            self::Other => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $kind) => [$kind->value => $kind->label()])->all();
    }
}
