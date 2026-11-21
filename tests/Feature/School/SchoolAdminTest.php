<?php

use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Models\User;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->admin = memberOf($this->school);
});

it('lists users with their roles and filters by role', function () {
    $teacher = memberOf($this->school, SchoolRole::Teacher, ['name' => 'Emeka Okonkwo']);
    memberOf($this->school, SchoolRole::Bursar, ['name' => 'Grace Udo']);

    $this->actingAs($this->admin)->get(route('school.members.index', $this->school))
        ->assertOk()->assertSee('Emeka Okonkwo')->assertSee('Grace Udo');

    $this->actingAs($this->admin)->get(route('school.members.index', [$this->school, 'role' => 'teacher']))
        ->assertOk()->assertSee('Emeka Okonkwo')->assertDontSee('Grace Udo');
});

it('disables and restores someone’s access without deleting them', function () {
    $teacher = memberOf($this->school, SchoolRole::Teacher);

    $this->actingAs($this->admin)->post(route('school.members.toggle', [$this->school, $teacher->ulid]))->assertRedirect();
    $this->actingAs($teacher)->get(route('school.dashboard', $this->school))->assertNotFound();

    $this->actingAs($this->admin)->post(route('school.members.toggle', [$this->school, $teacher->ulid]))->assertRedirect();
    $this->actingAs($teacher)->get(route('school.dashboard', $this->school))->assertOk();
});

it('will not let an admin lock themselves out, or touch people from other schools', function () {
    $outsider = memberOf(schoolWithRoles());

    $this->actingAs($this->admin)->post(route('school.members.toggle', [$this->school, $this->admin->ulid]))->assertStatus(422);
    $this->actingAs($this->admin)->post(route('school.members.toggle', [$this->school, $outsider->ulid]))->assertNotFound();
});

it('updates the school information', function () {
    $this->actingAs($this->admin)
        ->put(route('school.profile.update', $this->school), ['name' => 'Greenfield Academy, Abuja', 'motto' => 'Knowledge and character', 'state' => 'FCT', 'phone' => '+2348031234567', 'address' => '12 Aminu Kano Crescent'])
        ->assertSessionHasNoErrors();

    expect($this->school->fresh())->name->toBe('Greenfield Academy, Abuja')->motto->toBe('Knowledge and character');

    $this->actingAs(memberOf($this->school, SchoolRole::Principal))
        ->get(route('school.profile.edit', $this->school))
        ->assertForbidden();
});

it('shows the activity log to admins only', function () {
    $this->actingAs($this->admin)->get(route('school.dashboard', $this->school));

    $this->actingAs($this->admin)->get(route('school.activity.index', $this->school))->assertOk()->assertSee('signed in to the school');
    $this->actingAs(memberOf($this->school, SchoolRole::Teacher))->get(route('school.activity.index', $this->school))->assertForbidden();
});

it('lets a platform admin see a school’s details and suspend it', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($platformAdmin)->get(route('platform.schools.show', $this->school))->assertOk()->assertSee($this->school->name);

    $this->actingAs($platformAdmin)
        ->patch(route('platform.schools.status', $this->school), ['status' => SchoolStatus::Suspended->value])
        ->assertRedirect();

    expect($this->school->fresh()->status)->toBe(SchoolStatus::Suspended);
    $this->actingAs($this->admin)->get(route('school.dashboard', $this->school))->assertForbidden();
});
