<?php

namespace App\Enums;

enum ClassSection: string
{
    case Nursery = 'nursery';
    case Primary = 'primary';
    case Junior = 'junior';
    case Senior = 'senior';
    case Islamiyya = 'islamiyya';

    public function label(): string
    {
        return match ($this) {
            self::Nursery => 'Nursery',
            self::Primary => 'Primary',
            self::Junior => 'Junior Secondary',
            self::Senior => 'Senior Secondary',
            self::Islamiyya => 'Islamiyya',
        };
    }
}
