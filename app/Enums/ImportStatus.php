<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Checked = 'checked';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Checked => 'Checked, not imported',
            self::Queued => 'Waiting to start',
            self::Running => 'Importing',
            self::Completed => 'Imported',
            self::Failed => 'Stopped with an error',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed => 'naija',
            self::Failed => 'danger',
            self::Queued, self::Running => 'gold',
            self::Checked => 'neutral',
        };
    }

    public function isInProgress(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
