<?php

use App\Enums\SchoolRole;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->school = schoolWithStructure(['short_code' => 'GFA', 'slug' => 'greenfield']);
    $this->admin = memberOf($this->school);
});

function staffForm(array $overrides = []): array
{
    return [
        'title' => 'Mr.',
        'first_name' => 'Emeka',
        'last_name' => 'Okonkwo',
        'gender' => 'male',
        'email' => 'emeka@greenfield.test',
        'phone' => '0803 555 0101',
        'designation' => 'Mathematics Teacher',
        'employment_type' => 'full_time',
        'role' => 'teacher',
        ...$overrides,
    ];
}

it('adds a teacher with a staff number, a login and a set-password email', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post(route('school.staff.store', $this->school), staffForm())->assertRedirect();

    $staff = inSchool($this->school, fn () => Staff::query()->with('user')->sole());
    expect($staff->staff_no)->toBe('GFA/STF/001')
        ->and($staff->phone)->toBe('+2348035550101')
        ->and($staff->user->email)->toBe('emeka@greenfield.test');

    inSchool($this->school, fn () => expect($staff->user->hasRole(SchoolRole::Teacher))->toBeTrue());
    Notification::assertSentTo($staff->user, ResetPassword::class);
});

it('shows one-time credentials for a phone-only staff member', function () {
    $this->actingAs($this->admin)
        ->post(route('school.staff.store', $this->school), staffForm(['email' => null, 'role' => 'store-keeper']))
        ->assertSessionHas('credentials', fn (array $credentials) => $credentials[0]['login'] === '+2348035550101');
});

it('links someone who already works at another school to the same account', function () {
    $otherSchool = schoolWithStructure();
    $existing = staffOf($otherSchool, attributes: ['email' => 'emeka@greenfield.test']);

    $this->actingAs($this->admin)->post(route('school.staff.store', $this->school), staffForm())->assertRedirect();

    expect(User::query()->where('email', 'emeka@greenfield.test')->count())->toBe(1)
        ->and($existing->user->activeSchools()->count())->toBe(2);

    $this->actingAs($this->admin)
        ->post(route('school.staff.store', $this->school), staffForm())
        ->assertSessionHasErrors('email');
});

it('does not let staff be given parent or student roles', function () {
    $this->actingAs($this->admin)
        ->post(route('school.staff.store', $this->school), staffForm(['role' => 'parent']))
        ->assertSessionHasErrors('role');
});

it('shows the staff list and profile to the principal but not to teachers', function () {
    $staff = staffOf($this->school);
    $principal = memberOf($this->school, SchoolRole::Principal);

    $this->actingAs($principal)->get(route('school.staff.index', $this->school))->assertOk()->assertSee($staff->last_name);
    $this->actingAs($principal)->get(route('school.staff.show', [$this->school, $staff]))->assertOk();
    $this->actingAs($principal)->get(route('school.staff.create', $this->school))->assertForbidden();
    $this->actingAs($staff->user)->get(route('school.staff.index', $this->school))->assertForbidden();
});

it('lets an admin edit a staff member’s details and role', function () {
    $staff = staffOf($this->school, attributes: [
        'first_name' => 'Emeka',
        'last_name' => 'Okonkwo',
        'email' => 'emeka@greenfield.test',
        'phone' => '+2348035550101',
        'designation' => 'Mathematics Teacher',
    ]);

    $this->actingAs($this->admin)
        ->get(route('school.staff.edit', [$this->school, $staff]))
        ->assertOk()
        ->assertSee('Emeka');

    $this->actingAs($this->admin)
        ->put(route('school.staff.update', [$this->school, $staff]), [
            'title' => 'Mrs.',
            'first_name' => 'Ngozi',
            'last_name' => 'Okonkwo',
            'gender' => 'female',
            'email' => 'ngozi@greenfield.test',
            'phone' => '0803 555 0101',
            'designation' => 'Bursar',
            'department' => 'Accounts',
            'employment_type' => 'full_time',
            'status' => 'active',
            'role' => 'bursar',
        ])
        ->assertRedirect(route('school.staff.show', [$this->school, $staff]));

    $staff->refresh();

    expect($staff->first_name)->toBe('Ngozi')
        ->and($staff->designation)->toBe('Bursar')
        ->and($staff->email)->toBe('ngozi@greenfield.test')
        ->and($staff->user->email)->toBe('ngozi@greenfield.test');

    inSchool($this->school, fn () => expect($staff->user->fresh()->hasRole(SchoolRole::Bursar))->toBeTrue());
});

it('does not let a principal edit staff without staff.manage', function () {
    $staff = staffOf($this->school);
    $principal = memberOf($this->school, SchoolRole::Principal);

    $this->actingAs($principal)->get(route('school.staff.edit', [$this->school, $staff]))->assertForbidden();
});
