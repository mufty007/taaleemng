<?php

namespace App\Console\Commands;

use App\Support\Platform\Health;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Scheduled every minute: proves cron is running the scheduler (shown on the platform Health page).
 */
#[Signature('taaleem:heartbeat')]
#[Description('Record that the scheduler ran, for the platform Health page')]
class Heartbeat extends Command
{
    public function handle(): int
    {
        Cache::forever(Health::HEARTBEAT_KEY, now()->toIso8601String());

        return self::SUCCESS;
    }
}
