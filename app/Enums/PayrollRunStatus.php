<?php

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Prepared = 'prepared';
    case Paid = 'paid';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Prepared => 'Prepared',
            self::Paid => 'Paid',
            self::Voided => 'Voided',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Prepared => 'gold',
            self::Paid => 'naija',
            self::Voided => 'danger',
        };
    }
}
