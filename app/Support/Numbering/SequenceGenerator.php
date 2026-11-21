<?php

namespace App\Support\Numbering;

use App\Models\NumberSequence;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hands out gap-free, per-school document numbers such as GFA/2026/0042.
 *
 * Pattern tokens: {SCHOOL} short code, {YYYY} / {YY} current year, {SEQ:n} zero-padded counter.
 */
class SequenceGenerator
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  Carbon|null  $date  The document's own date (e.g. a backdated admission); defaults to today.
     */
    public function next(string $key, ?Carbon $date = null): string
    {
        $school = $this->currentSchool->getOrFail(NumberSequence::class);

        return DB::transaction(function () use ($key, $school, $date): string {
            $sequence = $this->lockedSequence($key);

            $number = $this->format($sequence->pattern, $sequence->next_value, $school->short_code, $date);
            $sequence->increment('next_value');

            return $number;
        });
    }

    public function format(string $pattern, int $value, string $schoolCode, ?Carbon $date = null): string
    {
        $date ??= now();

        $formatted = strtr($pattern, [
            '{SCHOOL}' => $schoolCode,
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
        ]);

        return preg_replace_callback(
            '/\{SEQ(?::(\d+))?\}/',
            fn (array $match): string => str_pad((string) $value, (int) ($match[1] ?? 1), '0', STR_PAD_LEFT),
            $formatted,
        );
    }

    private function lockedSequence(string $key): NumberSequence
    {
        $sequence = NumberSequence::query()->where('key', $key)->lockForUpdate()->first();

        if ($sequence) {
            return $sequence;
        }

        $pattern = config("taaleem.numbering.{$key}")
            ?? throw new InvalidArgumentException("No numbering pattern is configured for [{$key}].");

        try {
            NumberSequence::query()->create(['key' => $key, 'pattern' => $pattern, 'next_value' => 1]);
        } catch (UniqueConstraintViolationException) {
            // Another request created it first; fall through and lock theirs.
        }

        return NumberSequence::query()->where('key', $key)->lockForUpdate()->firstOrFail();
    }
}
