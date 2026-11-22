<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Key/value preferences for one school. Values are JSON; defaults live in config/taaleem.php.
 */
#[Fillable(['key', 'value'])]
class SchoolSetting extends Model
{
    use BelongsToSchool;

    /**
     * Keys the Settings screen and the app know about.
     *
     * @return list<string>
     */
    public static function knownKeys(): array
    {
        return [
            'admissions_open',
            'show_positions',
            'promotion_pass_mark',
            'paystack_subaccount',
            'payout_bank_code',
            'payout_bank_name',
            'payout_account_number',
            'payout_account_name',
            'currency',
            'date_format',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Read a setting for the school in context, falling back to config/taaleem.php defaults.
     */
    public static function valueFor(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting->value ?? $default ?? config("taaleem.settings.{$key}");
    }

    public static function store(string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
