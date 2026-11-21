<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receive = 'receive';
    case Issue = 'issue';
    case Sale = 'sale';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Stock in',
            self::Issue => 'Issued',
            self::Sale => 'Sold to student',
            self::Adjust => 'Adjustment',
        };
    }

    public function increasesStock(): bool
    {
        return $this === self::Receive;
    }
}
