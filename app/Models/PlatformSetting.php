<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One platform-wide setting (not per school). The value is encrypted with the app key,
 * so secrets such as the Paystack secret key and SMTP password are never stored in plain text.
 */
#[Fillable(['key', 'value'])]
class PlatformSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }
}
