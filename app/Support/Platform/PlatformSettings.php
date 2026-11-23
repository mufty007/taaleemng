<?php

namespace App\Support\Platform;

use App\Models\PlatformSetting;
use App\Support\Billing\PlanCatalog;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Platform-wide settings the super admin manages in the platform console: the Paystack
 * keys and the central SMTP account all schools' email goes out through.
 *
 * Saved values override the server's .env; anything not saved here falls back to .env.
 */
final class PlatformSettings
{
    /** @var list<string> */
    public const PAYSTACK = ['paystack_public_key', 'paystack_secret_key'];

    /** @var list<string> */
    public const MAIL = ['mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];

    /** @var list<string> Never shown back on screen. */
    public const SECRETS = ['paystack_secret_key', 'mail_password'];

    /**
     * Every saved setting, decrypted.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        try {
            return PlatformSetting::query()->whereNotNull('value')->pluck('value', 'key')->map(fn (mixed $value): string => (string) $value)->all();
        } catch (QueryException) {
            // Before the first migration (fresh install, composer scripts) there is no table yet.
            return [];
        } catch (Throwable) {
            // A value that no longer decrypts (APP_KEY changed) counts as not set.
            return [];
        }
    }

    public static function get(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Save settings. A null value removes the saved one, so .env applies again.
     *
     * @param  array<string, ?string>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                PlatformSetting::query()->where('key', $key)->delete();

                continue;
            }

            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Lay the saved settings over the app's config. Runs once as the app boots.
     */
    public static function apply(): void
    {
        $settings = self::all();

        // Remember what the server's own config said, so the settings page can show
        // "from .env" even when config is cached (env() returns null then).
        config(['platform.env' => [
            'paystack_public_key' => config('services.paystack.public_key'),
            'paystack_secret_key' => config('services.paystack.secret_key'),
            'mail_mailer' => config('mail.default'),
            'mail_host' => config('mail.default') === 'smtp' ? config('mail.mailers.smtp.host') : null,
            'mail_from_address' => config('mail.from.address'),
        ]]);

        if (filled($settings['paystack_secret_key'] ?? null)) {
            config([
                'services.paystack.secret_key' => $settings['paystack_secret_key'],
                'services.paystack.public_key' => $settings['paystack_public_key'] ?? config('services.paystack.public_key'),
            ]);
        }

        if (filled($settings['mail_host'] ?? null)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.url' => null,
                'mail.mailers.smtp.host' => $settings['mail_host'],
                'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? 587),
                // Port 465 needs TLS from the first byte (smtps); 587 upgrades with STARTTLS on its own.
                'mail.mailers.smtp.scheme' => ($settings['mail_encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
                'mail.mailers.smtp.password' => $settings['mail_password'] ?? null,
            ]);
        }

        if (filled($settings['mail_from_address'] ?? null)) {
            config(['mail.from.address' => $settings['mail_from_address']]);
        }

        if (filled($settings['mail_from_name'] ?? null)) {
            config(['mail.from.name' => $settings['mail_from_name']]);
        }

        // Subscription plan names, seats and prices edited in Billing → Plans.
        PlanCatalog::applySaved();
    }

    /**
     * Where a setting's value comes from, for the settings page.
     */
    public static function source(string $key): string
    {
        return match (true) {
            filled(self::get($key)) => 'saved',
            filled(config("platform.env.{$key}")) => 'env',
            default => 'none',
        };
    }

    /**
     * "•••• 3974" for a secret, so the admin can tell which key is in use without seeing it.
     */
    public static function mask(?string $secret): ?string
    {
        return filled($secret) ? '•••• '.substr((string) $secret, -4) : null;
    }
}
