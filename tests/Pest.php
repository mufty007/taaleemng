<?php

use App\Actions\Academic\ApplyNigerianDefaults;
use App\Actions\Academic\StartSession;
use App\Actions\People\AddStaffMember;
use App\Actions\People\EnrolmentResult;
use App\Actions\People\EnrolStudent;
use App\Actions\Platform\AddSchoolMember;
use App\Actions\Platform\SeedSchoolRoles;
use App\Enums\SchoolRole;
use App\Enums\SchoolType;
use App\Models\ClassArm;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
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
 * A school with roles, the standard Nigerian classes and subjects, and a current session.
 *
 * @param  array<string, mixed>  $attributes
 */
function schoolWithStructure(array $attributes = []): School
{
    $school = schoolWithRoles(['school_type' => SchoolType::Combined, ...$attributes]);

    inSchool($school, function () {
        app(ApplyNigerianDefaults::class)->handle();
        app(StartSession::class)->handle(2026, makeCurrent: true);
    });

    return $school;
}

/**
 * Run a callback with the school in context (for arranging or asserting tenant data).
 *
 * @template TReturn
 *
 * @param  callable(School): TReturn  $callback
 * @return TReturn
 */
function inSchool(School $school, callable $callback): mixed
{
    return app(CurrentSchool::class)->run($school, $callback);
}

/**
 * Add a staff member with a login and role to the school.
 *
 * @param  array<string, mixed>  $attributes
 */
function staffOf(School $school, SchoolRole $role = SchoolRole::Teacher, array $attributes = []): Staff
{
    return inSchool($school, fn () => app(AddStaffMember::class)->handle([
        'first_name' => 'Test',
        'last_name' => fake()->lastName(),
        'gender' => 'female',
        'email' => fake()->unique()->safeEmail(),
        'designation' => 'Teacher',
        ...$attributes,
    ], $role, 'password')['staff']);
}

/**
 * Enrol a student into a class arm of the school (by level name and arm), with a guardian.
 *
 * @param  array<string, mixed>  $student
 * @param  array<string, mixed>  $guardian
 */
function enrol(School $school, string $level = 'JSS 1', string $arm = 'A', array $student = [], array $guardian = []): EnrolmentResult
{
    return inSchool($school, function () use ($level, $arm, $student, $guardian) {
        $classArm = ClassArm::query()->where('name', $arm)->whereHas('level', fn ($query) => $query->where('name', $level))->firstOrFail();

        return app(EnrolStudent::class)->handle(
            ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'gender' => 'female', ...$student],
            $classArm,
            ['first_name' => 'Parent', 'last_name' => fake()->lastName(), 'relationship' => 'mother', 'phone' => '+23480'.fake()->unique()->numerify('########'), ...$guardian],
            'password',
        );
    });
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
