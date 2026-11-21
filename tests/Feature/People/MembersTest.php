<?php

use App\Actions\People\ChangeMemberRole;
use App\Enums\SchoolRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->school = schoolWithStructure(['short_code' => 'GFA', 'slug' => 'greenfield']);
    $this->admin = memberOf($this->school);
});

it('lists school members for admins and hides the page from teachers', function () {
    $teacher = staffOf($this->school);

    $this->actingAs($this->admin)
        ->get(route('school.members.index', $this->school))
        ->assertOk()
        ->assertSee($teacher->user->name);

    $this->actingAs($teacher->user)
        ->get(route('school.members.index', $this->school))
        ->assertForbidden();
});

it('changes a staff member’s role and keeps parent access when they have both', function () {
    $teacher = staffOf($this->school, attributes: [
        'email' => 'ada.teacher@greenfield.test',
        'phone' => '+2348035550199',
    ]);
    $enrolment = enrol($this->school, guardian: [
        'first_name' => 'Ada',
        'last_name' => 'Okonkwo',
        'email' => 'ada.teacher@greenfield.test',
        'phone' => '+2348035550199',
    ]);

    expect($enrolment->guardian->user->is($teacher->user))->toBeTrue();

    inSchool($this->school, function () use ($teacher) {
        expect($teacher->user->fresh()->hasRole(SchoolRole::Teacher))->toBeTrue()
            ->and($teacher->user->fresh()->hasRole(SchoolRole::Parent))->toBeTrue();
    });

    $this->actingAs($this->admin)
        ->post(route('school.members.role', [$this->school, $teacher->user->ulid]), ['role' => 'bursar'])
        ->assertRedirect()
        ->assertSessionHas('status');

    inSchool($this->school, function () use ($teacher) {
        $user = $teacher->user->fresh();
        $user->unsetRelation('roles');

        expect($user->hasRole(SchoolRole::Bursar))->toBeTrue()
            ->and($user->hasRole(SchoolRole::Teacher))->toBeFalse()
            ->and($user->hasRole(SchoolRole::Parent))->toBeTrue();
    });
});

it('refuses changing your own role', function () {
    $this->actingAs($this->admin)
        ->post(route('school.members.role', [$this->school, $this->admin->ulid]), ['role' => 'teacher'])
        ->assertSessionHasErrors('role');
});

it('keeps at least one school admin', function () {
    $otherAdmin = memberOf($this->school, SchoolRole::SchoolAdmin, [
        'name' => 'Second Admin',
        'email' => 'second@greenfield.test',
    ]);

    $this->actingAs($this->admin)
        ->post(route('school.members.role', [$this->school, $otherAdmin->ulid]), ['role' => 'teacher'])
        ->assertRedirect();

    inSchool($this->school, function () use ($otherAdmin) {
        expect($otherAdmin->fresh()->hasRole(SchoolRole::Teacher))->toBeTrue();

        expect(fn () => app(ChangeMemberRole::class)->handle($this->admin, SchoolRole::Teacher))
            ->toThrow(ValidationException::class, 'Keep at least one school admin');
    });
});

it('does not let parent or student roles be assigned from the users page', function () {
    $teacher = staffOf($this->school);

    $this->actingAs($this->admin)
        ->post(route('school.members.role', [$this->school, $teacher->user->ulid]), ['role' => 'parent'])
        ->assertSessionHasErrors('role');
});

it('does not change roles for parents who are not staff', function () {
    $enrolment = enrol($this->school);
    $parent = $enrolment->guardian->user;

    $this->actingAs($this->admin)
        ->post(route('school.members.role', [$this->school, $parent->ulid]), ['role' => 'teacher'])
        ->assertSessionHasErrors('role');
});

it('disables and restores access without deleting the user', function () {
    $teacher = staffOf($this->school);

    $this->actingAs($this->admin)
        ->post(route('school.members.toggle', [$this->school, $teacher->user->ulid]))
        ->assertRedirect();

    expect($teacher->user->fresh()->activeSchools()->whereKey($this->school->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($teacher->user->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('school.members.toggle', [$this->school, $teacher->user->ulid]))
        ->assertRedirect();

    expect($teacher->user->fresh()->activeSchools()->whereKey($this->school->id)->exists())->toBeTrue();
});
