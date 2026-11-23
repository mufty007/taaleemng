<?php

namespace App\Enums;

/**
 * How loud a platform broadcast is in schools.
 */
enum BroadcastLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
