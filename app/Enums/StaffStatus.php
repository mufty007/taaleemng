<?php

namespace App\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Exited = 'exited';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On leave',
            self::Exited => 'Left the school',
        };
    }
}
