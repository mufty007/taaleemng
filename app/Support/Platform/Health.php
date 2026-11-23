<?php

namespace App\Support\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Is the platform healthy? Each check says ok / warn / fail with a plain-English detail
 * and what to do about it. Shown on the platform Health page.
 */
final class Health
{
    public const HEARTBEAT_KEY = 'platform:scheduler-heartbeat';

    public const PAYSTACK_WEBHOOK_KEY = 'platform:paystack-webhook';

    /**
     * @return list<array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    public static function checks(): array
    {
        return [self::scheduler(), self::queue(), self::email(), self::paystack(), self::backups(), self::disk()];
    }

    /**
     * @return array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    public static function scheduler(): array
    {
        $beat = self::time(Cache::get(self::HEARTBEAT_KEY));

        return match (true) {
            $beat === null => self::check('Scheduler', 'clock', 'fail', 'Has never run. On the server, cron runs it every minute from /etc/cron.d/taaleem (deploy/setup-server.sh sets this up).'),
            $beat->lt(now()->subMinutes(5)) => self::check('Scheduler', 'clock', 'warn', 'Last ran '.$beat->diffForHumans().'. Check cron on the server.'),
            default => self::check('Scheduler', 'clock', 'ok', 'Running. Last ran '.$beat->diffForHumans().'.'),
        };
    }

    /**
     * @return array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    public static function queue(): array
    {
        $waiting = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $failed = DB::table('failed_jobs')->count();
        $stuck = $oldest !== null && Carbon::createFromTimestamp((int) $oldest)->lt(now()->subMinutes(10));

        return match (true) {
            $failed > 0 => self::check('Background jobs', 'list-checks', 'fail', "{$failed} failed (emails, imports). Retry them below once the cause is fixed. {$waiting} waiting."),
            $stuck => self::check('Background jobs', 'list-checks', 'warn', "{$waiting} waiting, the oldest for over 10 minutes. Is the queue worker running? (sudo supervisorctl status)"),
            default => self::check('Background jobs', 'list-checks', 'ok', $waiting === 0 ? 'Nothing waiting, nothing failed.' : "{$waiting} waiting, nothing failed."),
        };
    }

    /**
     * @return array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    public static function email(): array
    {
        $mailer = (string) config('mail.default');

        return $mailer === 'smtp' && filled(config('mail.mailers.smtp.host'))
            ? self::check('Email', 'mail', 'ok', 'Sending through '.config('mail.mailers.smtp.host').' as '.config('mail.from.address').'.')
            : self::check('Email', 'mail', 'warn', "Not sending: the mailer is “{$mailer}”. Set up SMTP in Settings so invitations and reminders reach people.");
    }

    /**
     * @return array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    public static function paystack(): array
    {
        $secret = (string) config('services.paystack.secret_key');
        $webhook = self::time(Cache::get(self::PAYSTACK_WEBHOOK_KEY));
        $heard = $webhook ? ' Last webhook '.$webhook->diffForHumans().'.' : ' No webhook received yet.';

        return match (true) {
            $secret === '' => self::check('Paystack', 'credit-card', 'warn', 'Not set up, so parents can’t pay online. Add the keys in Settings.'),
            str_starts_with($secret, 'sk_test_') => self::check('Paystack', 'credit-card', 'warn', 'In test mode: no real money moves.'.$heard),
            default => self::check('Paystack', 'credit-card', 'ok', 'Live.'.$heard),
        };
    }

    /**
     * @return array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    public static function backups(): array
    {
        $latest = self::latestBackup();

        return match (true) {
            $latest === null => self::check('Backups', 'database-backup', 'fail', 'No backups found in '.config('taaleem.backup_path').'. deploy/backup.sh runs nightly at 02:30 once set up.'),
            $latest['at']->lt(now()->subHours(26)) => self::check('Backups', 'database-backup', 'warn', 'The newest backup is from '.$latest['at']->diffForHumans().'. Check the backup cron and its log.'),
            default => self::check('Backups', 'database-backup', 'ok', 'Last backup '.$latest['at']->diffForHumans().' ('.$latest['size'].').'),
        };
    }

    /**
     * @return array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    public static function disk(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if (! $free || ! $total) {
            return self::check('Disk space', 'hard-drive', 'warn', 'Could not read the disk size.');
        }

        $share = $free / $total * 100;
        $detail = self::bytes((int) $free).' free of '.self::bytes((int) $total).' ('.round($share).'%).';

        return self::check('Disk space', 'hard-drive', match (true) {
            $share < 5 => 'fail',
            $share < 10 => 'warn',
            default => 'ok',
        }, $detail);
    }

    /**
     * @return array{at: Carbon, size: string}|null
     */
    public static function latestBackup(): ?array
    {
        $path = (string) config('taaleem.backup_path');

        if (! File::isDirectory($path)) {
            return null;
        }

        $newest = collect(File::glob(rtrim($path, '/\\').'/db-*.sql.gz') ?: [])
            ->sortByDesc(fn (string $file): int => (int) filemtime($file))
            ->first();

        return $newest ? ['at' => Carbon::createFromTimestamp((int) filemtime($newest)), 'size' => self::bytes((int) filesize($newest))] : null;
    }

    /**
     * What is running: PHP, Laravel, environment and the deployed release.
     *
     * @return array<string, string>
     */
    public static function versions(): array
    {
        $release = null;

        try {
            $release = json_decode((string) File::get(storage_path('app/release.json')), true);
        } catch (Throwable) {
            // No release file outside a server deployed with deploy/deploy.sh.
        }

        return array_filter([
            'Release' => is_array($release) ? trim(($release['commit'] ?? '').' '.($release['message'] ?? '')) : 'Not deployed with deploy.sh',
            'Deployed' => is_array($release) && isset($release['deployed_at']) ? Carbon::parse($release['deployed_at'])->format('j M Y, H:i') : null,
            'Environment' => (string) app()->environment().(config('app.debug') ? ' (debug on)' : ''),
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
        ]);
    }

    /**
     * @param  'ok'|'warn'|'fail'  $status
     * @return array{label: string, icon: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private static function check(string $label, string $icon, string $status, string $detail): array
    {
        return compact('label', 'icon', 'status', 'detail');
    }

    private static function time(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    private static function bytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return round($bytes, 1).' PB';
    }
}
