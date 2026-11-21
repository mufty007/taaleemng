<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
    case Transferred = 'transferred';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Graduated => 'Graduated',
            self::Withdrawn => 'Withdrawn',
            self::Transferred => 'Transferred',
            self::Suspended => 'Suspended',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'naija',
            self::Graduated => 'brand',
            self::Suspended => 'danger',
            default => 'neutral',
        };
    }
}
