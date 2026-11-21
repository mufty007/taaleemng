<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartPaid = 'part_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartPaid => 'Part paid',
            self::Paid => 'Paid',
            self::Void => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::PartPaid => 'gold',
            self::Paid => 'naija',
            self::Void => 'neutral',
        };
    }

    /**
     * Whether money is still owed on it.
     */
    public function isOpen(): bool
    {
        return $this === self::Unpaid || $this === self::PartPaid;
    }
}
