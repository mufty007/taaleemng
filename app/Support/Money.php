<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Naira amounts. Everything is stored in kobo (integers) and only turned into
 * naira for people to read: "₦185,000", or in words on a receipt.
 */
final class Money
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = [1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand'];

    /**
     * "₦185,000" (kobo shown only when there are some).
     */
    public static function format(int $kobo): string
    {
        $sign = $kobo < 0 ? '−' : '';
        $kobo = abs($kobo);

        return $sign.'₦'.number_format(intdiv($kobo, 100)).($kobo % 100 ? '.'.str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT) : '');
    }

    /**
     * "₦1.2m", "₦850k": short amounts for chart labels.
     */
    public static function compact(int $kobo): string
    {
        $naira = intdiv(abs($kobo), 100);

        return ($kobo < 0 ? '−' : '').'₦'.match (true) {
            $naira >= 1_000_000 => rtrim(rtrim(number_format($naira / 1_000_000, 1), '0'), '.').'m',
            $naira >= 1_000 => round($naira / 1_000).'k',
            default => (string) $naira,
        };
    }

    /**
     * Turn what someone typed ("185,000", "185000.50", "₦185 000") into kobo.
     */
    public static function toKobo(string|int|float $naira): int
    {
        $clean = is_string($naira) ? (string) preg_replace('/[^\d.]/', '', $naira) : (string) $naira;

        if ($clean === '' || ! is_numeric($clean)) {
            throw new InvalidArgumentException("“{$naira}” is not an amount.");
        }

        return (int) round(((float) $clean) * 100);
    }

    public static function toNaira(int $kobo): string
    {
        return $kobo % 100 === 0 ? (string) intdiv($kobo, 100) : number_format($kobo / 100, 2, '.', '');
    }

    /**
     * "One hundred and eighty-five thousand naira only", the way receipts say it.
     */
    public static function inWords(int $kobo): string
    {
        $naira = intdiv(abs($kobo), 100);
        $remainder = abs($kobo) % 100;

        $words = ($naira === 0 ? 'zero' : self::spell($naira)).' naira';

        if ($remainder > 0) {
            $words .= ', '.self::spell($remainder).' kobo';
        }

        return ucfirst($words).' only';
    }

    private static function spell(int $number): string
    {
        $parts = [];

        foreach (self::SCALES as $size => $name) {
            if ($number >= $size) {
                $parts[] = self::spell(intdiv($number, $size)).' '.$name;
                $number %= $size;
            }
        }

        if ($number >= 100) {
            $parts[] = self::ONES[intdiv($number, 100)].' hundred'.($number % 100 ? ' and '.self::belowHundred($number % 100) : '');
        } elseif ($number > 0 && $parts !== []) {
            // "One thousand and one", not "one thousand, one".
            return implode(', ', $parts).' and '.self::belowHundred($number);
        } elseif ($number > 0) {
            $parts[] = self::belowHundred($number);
        }

        return implode(', ', $parts);
    }

    private static function belowHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        return self::TENS[intdiv($number, 10)].($number % 10 ? '-'.self::ONES[$number % 10] : '');
    }
}
