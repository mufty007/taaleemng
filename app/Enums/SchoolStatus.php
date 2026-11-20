<?php

namespace App\Enums;

enum SchoolStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived',
        };
    }

    /**
     * Whether staff, parents and students may use the school's app.
     */
    public function allowsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }
}
