<?php

use App\Support\Money;

it('shows naira the way Nigerians write it', function (int $kobo, string $expected) {
    expect(Money::format($kobo))->toBe($expected);
})->with([
    [18_500_000, '₦185,000'],
    [18_500_050, '₦185,000.50'],
    [0, '₦0'],
    [-250_000, '−₦2,500'],
]);

it('reads amounts as people type them', function (string|int $typed, int $kobo) {
    expect(Money::toKobo($typed))->toBe($kobo);
})->with([
    ['185,000', 18_500_000],
    ['₦185 000.5', 18_500_050],
    ['0.10', 10],
    [2500, 250_000],
]);

it('refuses something that is not an amount', function () {
    Money::toKobo('abc');
})->throws(InvalidArgumentException::class);

it('writes amounts in words for receipts', function (int $kobo, string $expected) {
    expect(Money::inWords($kobo))->toBe($expected);
})->with([
    [18_500_000, 'One hundred and eighty-five thousand naira only'],
    [25_000_000, 'Two hundred and fifty thousand naira only'],
    [100_100, 'One thousand and one naira only'],
    [1_234_567_800, 'Twelve million, three hundred and forty-five thousand, six hundred and seventy-eight naira only'],
    [5_050, 'Fifty naira, fifty kobo only'],
    [0, 'Zero naira only'],
]);
