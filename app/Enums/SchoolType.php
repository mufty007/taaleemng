<?php

namespace App\Enums;

enum SchoolType: string
{
    case NurseryPrimary = 'nursery_primary';
    case Secondary = 'secondary';
    case Combined = 'combined';
    case Islamiyya = 'islamiyya';

    public function label(): string
    {
        return match ($this) {
            self::NurseryPrimary => 'Nursery & Primary',
            self::Secondary => 'Secondary (JSS & SSS)',
            self::Combined => 'Nursery to SSS',
            self::Islamiyya => 'Islamiyya / Qur’anic',
        };
    }
}
