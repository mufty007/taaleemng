<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Pos = 'pos';
    case Cheque = 'cheque';
    case Paystack = 'paystack';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Transfer => 'Bank transfer',
            self::Pos => 'POS',
            self::Cheque => 'Cheque',
            self::Paystack => 'Online (Paystack)',
        };
    }

    /**
     * Methods the bursar records by hand, as opposed to online payments.
     *
     * @return list<self>
     */
    public static function offline(): array
    {
        return [self::Cash, self::Transfer, self::Pos, self::Cheque];
    }
}
