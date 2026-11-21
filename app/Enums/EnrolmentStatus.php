<?php

namespace App\Enums;

enum EnrolmentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Promoted => 'Promoted',
            self::Repeated => 'Repeated',
            self::Withdrawn => 'Withdrawn',
            self::Graduated => 'Graduated',
        };
    }
}
