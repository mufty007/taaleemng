<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case Everyone = 'everyone';
    case Staff = 'staff';
    case Parents = 'parents';
    case Students = 'students';
    case ClassArm = 'class';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Staff => 'Staff',
            self::Parents => 'Parents',
            self::Students => 'Students',
            self::ClassArm => 'One class',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Everyone => 'megaphone',
            self::Staff => 'briefcase',
            self::Parents => 'users',
            self::Students => 'graduation-cap',
            self::ClassArm => 'layers',
        };
    }
}
