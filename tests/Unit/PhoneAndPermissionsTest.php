<?php

use App\Enums\SchoolRole;
use App\Models\User;
use App\Support\Permissions\PermissionCatalogue;

it('normalises Nigerian phone numbers to E.164', function (?string $input, ?string $expected) {
    expect(User::normalisePhone($input))->toBe($expected);
})->with([
    'local with spaces' => ['0803 123 4567', '+2348031234567'],
    'international' => ['+234 803 123 4567', '+2348031234567'],
    'no plus' => ['2348031234567', '+2348031234567'],
    'without leading zero' => ['8031234567', '+2348031234567'],
    'too short' => ['0803123', null],
    'empty' => ['', null],
    'null' => [null, null],
]);

it('only grants role templates permissions that exist in the catalogue', function (SchoolRole $role) {
    expect(array_diff(PermissionCatalogue::forRole($role), PermissionCatalogue::all()))->toBe([]);
})->with(SchoolRole::cases());

it('keeps portal permissions away from staff admins and finance away from teachers', function () {
    expect(PermissionCatalogue::forRole(SchoolRole::SchoolAdmin))->not->toContain('portal.wards')
        ->and(PermissionCatalogue::forRole(SchoolRole::Teacher))->not->toContain('fees.manage')
        ->and(PermissionCatalogue::forRole(SchoolRole::Parent))->toBe(['portal.wards']);
});
