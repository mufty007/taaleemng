<?php

use App\Enums\SchoolRole;
use Spatie\Permission\Models\Role;

it('brings an existing school’s roles up to date with the permission catalogue', function () {
    $school = schoolWithStructure();
    $principal = memberOf($school, SchoolRole::Principal);

    inSchool($school, fn () => Role::findByName(SchoolRole::Principal->value, 'web')->revokePermissionTo('staff.manage'));
    inSchool($school, fn () => expect($principal->fresh()->can('staff.manage'))->toBeFalse());

    $this->artisan('taaleem:sync-roles')
        ->expectsOutputToContain('Refreshed role templates for 1 school.')
        ->assertSuccessful();

    inSchool($school, fn () => expect($principal->fresh()->can('staff.manage'))->toBeTrue());
});
