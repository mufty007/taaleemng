<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Excused => 'Excused',
        };
    }

    /**
     * The letter used on the register.
     */
    public function short(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Late => 'L',
            self::Absent => 'A',
            self::Excused => 'E',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'naija',
            self::Late => 'gold',
            self::Absent => 'danger',
            self::Excused => 'sky',
        };
    }

    /**
     * Whether the day counts as "times present" on the report card.
     */
    public function isPresent(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
