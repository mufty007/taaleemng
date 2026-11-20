<?php

use App\Actions\Platform\AddSchoolMember;
use App\Actions\Platform\SeedSchoolRoles;
use App\Enums\SchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A school with its role templates seeded.
 *
 * @param  array<string, mixed>  $attributes
 */
function schoolWithRoles(array $attributes = []): School
{
    $school = School::factory()->create($attributes);

    app(SeedSchoolRoles::class)->handle($school);

    return $school;
}

/**
 * A user who is an active member of the school with the given role.
 *
 * @param  array<string, mixed>  $attributes
 */
function memberOf(School $school, SchoolRole $role = SchoolRole::SchoolAdmin, array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    app(AddSchoolMember::class)->handle($school, $user, $role);

    return $user;
}
