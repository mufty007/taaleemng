<?php

namespace App\Support\Results;

use App\Enums\PromotionDecision;
use App\Models\SchoolSetting;

/**
 * Suggests the end-of-session decision from a student's cumulative average.
 * The principal can always override it on the report card.
 */
class PromotionRules
{
    /**
     * Students within this many marks below the pass mark go up on probation.
     */
    public const PROBATION_MARGIN = 5;

    public static function passMark(): float
    {
        return (float) SchoolSetting::valueFor('promotion_pass_mark');
    }

    /**
     * Students in the school’s last class graduate whatever their average.
     *
     * @param  bool  $finalClass  whether students leave the school after this class
     */
    public static function suggest(?float $cumulativeAverage, bool $finalClass, float $passMark): ?PromotionDecision
    {
        if ($cumulativeAverage === null) {
            return null;
        }

        return match (true) {
            $finalClass => PromotionDecision::Graduated,
            $cumulativeAverage >= $passMark => PromotionDecision::Promoted,
            $cumulativeAverage >= $passMark - self::PROBATION_MARGIN => PromotionDecision::Probation,
            default => PromotionDecision::Repeat,
        };
    }
}
