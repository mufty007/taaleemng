<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for confirmation',
            self::Confirmed => 'Confirmed',
            self::Failed => 'Failed',
            self::Reversed => 'Reversed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'gold',
            self::Confirmed => 'naija',
            self::Failed => 'danger',
            self::Reversed => 'neutral',
        };
    }
}
