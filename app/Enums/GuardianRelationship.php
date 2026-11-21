<?php

namespace App\Enums;

enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Grandparent = 'grandparent';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Sibling = 'sibling';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Guardian => 'Guardian',
            self::Grandparent => 'Grandparent',
            self::Uncle => 'Uncle',
            self::Aunt => 'Aunt',
            self::Sibling => 'Older sibling',
            self::Other => 'Other',
        };
    }
}
