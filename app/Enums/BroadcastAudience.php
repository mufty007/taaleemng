<?php

namespace App\Enums;

/**
 * Who in each school sees a platform broadcast.
 */
enum BroadcastAudience: string
{
    case Admins = 'admins';
    case Staff = 'staff';
    case Everyone = 'everyone';

    public function label(): string
    {
        return match ($this) {
            self::Admins => 'School admins',
            self::Staff => 'All staff',
            self::Everyone => 'Everyone, including parents and students',
        };
    }
}
