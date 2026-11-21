<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Readable one-time passwords that a school office can print or read out
 * over the phone, e.g. "mango-4827". Users are asked to change them.
 */
final class TemporaryPassword
{
    private const WORDS = [
        'mango', 'cashew', 'baobab', 'river', 'eagle', 'falcon', 'palm', 'savanna', 'harmattan', 'kola',
        'zobo', 'chalk', 'lantern', 'garden', 'market', 'drum', 'sunrise', 'cocoa', 'ocean', 'pepper',
    ];

    public static function generate(): string
    {
        return Arr::random(self::WORDS).'-'.random_int(1000, 9999);
    }
}
