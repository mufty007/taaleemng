<?php

use App\Enums\SchoolRole;
use App\Models\ClassArm;
use Spatie\Activitylog\Models\Activity;

it('greets the user and shows the school admin a setup checklist', function () {
    $school = schoolWithRoles(['name' => 'Greenfield Academy']);
    $admin = memberOf($school, SchoolRole::SchoolAdmin, ['name' => 'Ngozi Okafor']);
    $principal = memberOf($school, SchoolRole::Principal, ['name' => 'Funke Adebayo']);

    $this->actingAs($admin)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Ngozi')
        ->assertSee('Get your school ready');

    $this->actingAs($principal)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Principal / Head Teacher')
        ->assertDontSee('Get your school ready');
});

it('shows a teacher their own classes and numbers', function () {
    $school = schoolWithStructure();
    $teacher = staffOf($school);
    enrol($school, 'JSS 1', 'A');
    enrol($school, 'SSS 2', 'A');

    inSchool($school, fn () => ClassArm::query()
        ->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole()
        ->update(['form_teacher_id' => $teacher->id]));

    $this->actingAs($teacher->user)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('My classes')
        ->assertSee('My students')
        ->assertSee('Form teacher');
});

it('only shows sidebar modules the role is allowed to use', function () {
    $school = schoolWithRoles();

    $bursar = memberOf($school, SchoolRole::Bursar);
    $teacher = memberOf($school, SchoolRole::Teacher);

    $this->actingAs($bursar)->get(route('school.dashboard', $school))
        ->assertSee('Debtors')
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
