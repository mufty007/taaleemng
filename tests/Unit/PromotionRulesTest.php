<?php

use App\Enums\PromotionDecision;
use App\Support\Results\PromotionRules;

it('suggests an end-of-session decision from the cumulative average', function (?float $average, bool $finalClass, ?PromotionDecision $expected) {
    expect(PromotionRules::suggest($average, $finalClass, passMark: 40))->toBe($expected);
})->with([
    'well above the pass mark' => [72.5, false, PromotionDecision::Promoted],
    'exactly the pass mark' => [40.0, false, PromotionDecision::Promoted],
    'just below' => [36.0, false, PromotionDecision::Probation],
    'at the probation line' => [35.0, false, PromotionDecision::Probation],
    'well below' => [28.4, false, PromotionDecision::Repeat],
    'last class graduates' => [28.4, true, PromotionDecision::Graduated],
    'no results' => [null, false, null],
]);

it('only moves promoted students up a class', function () {
    expect(PromotionDecision::Promoted->movesUp())->toBeTrue()
        ->and(PromotionDecision::Probation->movesUp())->toBeTrue()
        ->and(PromotionDecision::Repeat->movesUp())->toBeFalse()
        ->and(PromotionDecision::Graduated->movesUp())->toBeFalse();
});
