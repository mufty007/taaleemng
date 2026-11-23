<?php

namespace App\Enums;

/**
 * Whether each class level has lettered arms (JSS 1A, JSS 1B) or a single class.
 */
enum ClassStructure: string
{
    case Arms = 'arms';
    case Single = 'single';

    public function label(): string
    {
        return match ($this) {
            self::Arms => 'A, B, C arms',
            self::Single => 'One class per level',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $structure) => [$structure->value => $structure->label()])->all();
    }
}
