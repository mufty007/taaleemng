<?php

namespace App\Enums;

/**
 * Where a Ta'aleem invoice to a school stands.
 */
enum SchoolInvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'gold',
            self::Paid => 'naija',
            self::Void => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }
}
