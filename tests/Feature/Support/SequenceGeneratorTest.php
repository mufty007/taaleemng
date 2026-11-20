<?php

use App\Support\Numbering\SequenceGenerator;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Carbon;

it('formats every pattern token', function () {
    $generator = app(SequenceGenerator::class);

    expect($generator->format('{SCHOOL}/{YYYY}/{SEQ:4}', 42, 'GFA', Carbon::create(2026, 9, 14)))->toBe('GFA/2026/0042')
        ->and($generator->format('INV/{YY}/{SEQ:5}', 7, 'GFA', Carbon::create(2026, 9, 14)))->toBe('INV/26/00007')
        ->and($generator->format('R-{SEQ}', 12345, 'GFA'))->toBe('R-12345');
});

it('hands out consecutive numbers per school, independently', function () {
    $this->travelTo(Carbon::create(2026, 9, 14));

    $greenfield = schoolWithRoles(['short_code' => 'GFA']);
    $alNoor = schoolWithRoles(['short_code' => 'ANM']);
    $current = app(CurrentSchool::class);
    $next = fn () => app(SequenceGenerator::class)->next('admission_no');

    expect($current->run($greenfield, $next))->toBe('GFA/2026/0001')
        ->and($current->run($greenfield, $next))->toBe('GFA/2026/0002')
        ->and($current->run($alNoor, $next))->toBe('ANM/2026/0001')
        ->and($current->run($greenfield, $next))->toBe('GFA/2026/0003');
});

it('refuses an unknown sequence', function () {
    app(CurrentSchool::class)->run(schoolWithRoles(), fn () => app(SequenceGenerator::class)->next('nonsense'));
})->throws(InvalidArgumentException::class);
