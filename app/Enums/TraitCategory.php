<?php

namespace App\Enums;

enum TraitCategory: string
{
    case Affective = 'affective';
    case Psychomotor = 'psychomotor';

    public function label(): string
    {
        return match ($this) {
            self::Affective => 'Affective traits',
            self::Psychomotor => 'Psychomotor skills',
        };
    }
}
