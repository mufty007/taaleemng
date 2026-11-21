<?php

use App\Actions\Results\SetUpAssessment;
use App\Models\GradingScheme;
use App\Support\Results\ResultCalculator;

it('ranks highest first and lets ties share a position', function () {
    expect(ResultCalculator::rank(['ada' => 50.0, 'bayo' => 70.0, 'chidi' => 70.0, 'dayo' => 40.0, 'emeka' => 70.0]))
        ->toBe(['bayo' => 1, 'chidi' => 1, 'emeka' => 1, 'ada' => 4, 'dayo' => 5]);
});

it('ranks an empty class as nobody', function () {
    expect(ResultCalculator::rank([]))->toBe([]);
});

it('writes positions the way a report card does', function (int $number, string $expected) {
    expect(ResultCalculator::ordinal($number))->toBe($expected);
})->with([
    [1, '1st'], [2, '2nd'], [3, '3rd'], [4, '4th'], [11, '11th'], [12, '12th'], [13, '13th'],
    [21, '21st'], [22, '22nd'], [23, '23rd'], [101, '101st'], [111, '111th'],
]);

it('grades totals on the WAEC scale', function (float $total, string $grade) {
    $scheme = new GradingScheme(['name' => 'WAEC', 'bands' => SetUpAssessment::WAEC_BANDS]);

    expect($scheme->gradeFor($total)['grade'])->toBe($grade);
})->with([
    [100, 'A1'], [75, 'A1'], [74.9, 'B2'], [65, 'B3'], [60, 'C4'], [50, 'C6'], [49.5, 'D7'], [40, 'E8'], [39.9, 'F9'], [0, 'F9'],
]);

it('treats D7 and below as not a credit', function () {
    $scheme = new GradingScheme(['name' => 'WAEC', 'bands' => SetUpAssessment::WAEC_BANDS]);

    expect($scheme->isCredit('C6'))->toBeTrue()
        ->and($scheme->isCredit('D7'))->toBeFalse()
        ->and($scheme->isCredit('F'))->toBeFalse();
});
