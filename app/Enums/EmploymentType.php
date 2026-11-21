<?php

namespace App\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Nysc = 'nysc';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full time',
            self::PartTime => 'Part time',
            self::Contract => 'Contract',
            self::Nysc => 'NYSC corps member',
        };
    }
}
