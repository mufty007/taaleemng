<?php

use App\Actions\Platform\AddSchoolMember;
use App\Enums\SchoolRole;
use App\Models\User;

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Email or phone number');
});

it('logs in with an email address', function () {
    $school = schoolWithRoles();
    $user = memberOf($school, attributes: ['email' => 'proprietor@school.test']);

    $this->post(route('login.store'), ['login' => 'Proprietor@School.test', 'password' => 'password'])
        ->assertRedirect('/app');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('logs in with a Nigerian phone number typed the local way', function () {
    $school = schoolWithRoles();
    $parent = memberOf($school, SchoolRole::Parent, ['email' => null, 'phone' => '+2348021234567']);

    $this->post(route('login.store'), ['login' => '0802 123 4567', 'password' => 'password'])
        ->assertRedirect('/app');

    $this->assertAuthenticatedAs($parent);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['email' => 'teacher@school.test']);

    $this->post(route('login.store'), ['login' => 'teacher@school.test', 'password' => 'not-it'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('sends a member of one school straight to its dashboard', function () {
    $school = schoolWithRoles(['slug' => 'greenfield']);
    $user = memberOf($school);

    $this->actingAs($user)->get(route('app'))->assertRedirect('/app/greenfield');
});

it('asks a member of several schools which one to open, then remembers the last one', function () {
    $first = schoolWithRoles(['name' => 'Alpha Academy', 'slug' => 'alpha']);
    $second = schoolWithRoles(['name' => 'Beta College', 'slug' => 'beta']);
    $teacher = memberOf($first, SchoolRole::Teacher);
    app(AddSchoolMember::class)->handle($second, $teacher, SchoolRole::Teacher);

    $this->actingAs($teacher)->get(route('app'))->assertRedirect(route('schools.pick'));

    $this->actingAs($teacher)->get(route('schools.pick'))
        ->assertOk()
        ->assertSee('Alpha Academy')
        ->assertSee('Beta College');

    $this->actingAs($teacher)->get('/app/beta')->assertOk();
    $this->actingAs($teacher)->get(route('app'))->assertRedirect('/app/beta');
});

it('sends a platform admin with no school to the platform console', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)->get(route('app'))->assertRedirect(route('platform.dashboard'));
});

it('tells a user with no school what to do next', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('app'))
        ->assertOk()
        ->assertSee('You’re not linked to a school yet', false);
});

it('updates the profile with a phone number in local format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('user-profile-information.update'), [
            'name' => 'Amina Bello',
            'email' => $user->email,
            'phone' => '0803 123 4567',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Amina Bello')
        ->phone->toBe('+2348031234567');
});
