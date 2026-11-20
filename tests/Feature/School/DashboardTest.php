<?php

use App\Enums\SchoolRole;
use Spatie\Activitylog\Models\Activity;

it('greets the user and shows the setup checklist', function () {
    $school = schoolWithRoles(['name' => 'Greenfield Academy']);
    $user = memberOf($school, SchoolRole::Principal, ['name' => 'Funke Adebayo']);

    $this->actingAs($user)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Funke')
        ->assertSee('Principal / Head Teacher')
        ->assertSee('Get your school ready');
});

it('only shows sidebar modules the role is allowed to use', function () {
    $school = schoolWithRoles();

    $bursar = memberOf($school, SchoolRole::Bursar);
    $teacher = memberOf($school, SchoolRole::Teacher);

    $this->actingAs($bursar)->get(route('school.dashboard', $school))
        ->assertSee('School fees')
        ->assertDontSee('Score entry');

    $this->actingAs($teacher)->get(route('school.dashboard', $school))
        ->assertSee('Score entry')
        ->assertDontSee('Payroll');
});

it('records sign-ins in the activity log with the client IP', function () {
    $school = schoolWithRoles();
    $user = memberOf($school);

    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password']);

    $activity = Activity::query()->where('description', 'logged in')->sole();

    expect($activity->causer_id)->toBe($user->id)
        ->and($activity->ip_address)->toBe('127.0.0.1');
});
