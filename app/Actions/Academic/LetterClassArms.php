<?php

namespace App\Actions\Academic;

use App\Models\ClassArm;
use App\Models\ClassLevel;

/**
 * The way back from single classes: a class with no letter ("JSS 1", or "SSS 1 Science")
 * becomes arm A, B, C… so the school can add more arms beside it.
 */
class LetterClassArms
{
    public function handle(ClassLevel $level): void
    {
        $arms = $level->arms()->with('stream')->orderBy('id')->get();
        $used = $arms->pluck('name')->filter(fn (string $name) => preg_match('/^[A-Z]$/', $name) === 1)->all();
        $next = 'A';

        $arms
            ->filter(fn (ClassArm $arm) => $arm->name === '' || $arm->name === $arm->stream?->name)
            ->each(function (ClassArm $arm) use (&$used, &$next): void {
                while (in_array($next, $used, true)) {
                    $next++;
                }

                $arm->update(['name' => $next]);
                $used[] = $next;
            });
    }
}
