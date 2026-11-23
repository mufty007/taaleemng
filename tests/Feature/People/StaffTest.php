<?php

use App\Enums\SchoolRole;
use App\Models\ClassArm;
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
    $this->actingAs($staff->user)->get(route('school.staff.index', $this->school))->assertForbidden();
    $this->actingAs($staff->user)->get(route('school.staff.create', $this->school))->assertForbidden();
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

it('lets a principal add a teacher and print their ID card', function () {
    Notification::fake();
    $principal = memberOf($this->school, SchoolRole::Principal);

    $this->actingAs($principal)->get(route('school.staff.create', $this->school))
        ->assertOk()
        ->assertSee('Head Teacher')
        ->assertDontSee('Bursar / Accountant')
        ->assertDontSee('School Admin')
        ->assertDontSee('Basic salary');

    $this->actingAs($principal)->post(route('school.staff.store', $this->school), staffForm())->assertRedirect();

    $staff = inSchool($this->school, fn () => Staff::query()->with('user')->sole());
    inSchool($this->school, fn () => expect($staff->user->hasRole(SchoolRole::Teacher))->toBeTrue());

    $this->actingAs($principal)->get(route('school.id-cards.staff', $this->school))->assertOk()->assertSee('Emeka Okonkwo');
});

it('does not let a principal hand out roles with more access than their own', function (string $role) {
    $principal = memberOf($this->school, SchoolRole::Principal);

    $this->actingAs($principal)
        ->post(route('school.staff.store', $this->school), staffForm(['role' => $role]))
        ->assertSessionHasErrors('role');

    expect(inSchool($this->school, fn () => Staff::query()->count()))->toBe(0);
})->with(['school-admin', 'bursar', 'store-keeper']);

it('lets a principal edit teachers but not the bursar or a school admin', function () {
    $principal = memberOf($this->school, SchoolRole::Principal);
    $teacher = staffOf($this->school, attributes: ['first_name' => 'Emeka']);
    $bursar = staffOf($this->school, SchoolRole::Bursar);
    $proprietor = staffOf($this->school, SchoolRole::SchoolAdmin);

    $this->actingAs($principal)->get(route('school.staff.edit', [$this->school, $teacher]))->assertOk();
    $this->actingAs($principal)->get(route('school.staff.show', [$this->school, $teacher]))->assertSee('Edit');

    foreach ([$bursar, $proprietor] as $staff) {
        $this->actingAs($principal)->get(route('school.staff.edit', [$this->school, $staff]))->assertForbidden();
        $this->actingAs($principal)
            ->put(route('school.staff.update', [$this->school, $staff]), staffForm(['email' => 'takeover@example.test', 'status' => 'active', 'role' => 'teacher']))
            ->assertForbidden();
        expect($staff->user->fresh()->email)->not->toBe('takeover@example.test');
    }
});

it('keeps pay and bank details away from staff managers who do not run payroll', function () {
    $principal = memberOf($this->school, SchoolRole::Principal);
    $staff = staffOf($this->school, attributes: [
        'first_name' => 'Emeka',
        'last_name' => 'Okonkwo',
        'email' => 'emeka@greenfield.test',
        'basic_salary' => 18_000_000,
        'bank_name' => 'Access Bank',
        'bank_account_number' => '0123456789',
    ]);

    $this->actingAs($principal)->get(route('school.staff.show', [$this->school, $staff]))
        ->assertOk()
        ->assertDontSee('₦180,000')
        ->assertDontSee('0123456789');
    $this->actingAs($this->admin)->get(route('school.staff.show', [$this->school, $staff]))
        ->assertSee('₦180,000')
        ->assertSee('0123456789');

    $this->actingAs($principal)
        ->put(route('school.staff.update', [$this->school, $staff]), staffForm([
            'designation' => 'Head of Mathematics',
            'status' => 'active',
            'basic_salary' => '999,999',
            'bank_account_number' => '9999999999',
        ]))
        ->assertRedirect(route('school.staff.show', [$this->school, $staff]));

    $staff->refresh();
    expect($staff->designation)->toBe('Head of Mathematics')
        ->and($staff->basic_salary)->toBe(18_000_000)
        ->and($staff->bank_account_number)->toBe('0123456789');
});

it('narrows the staff list to teaching or support staff, a department or a status', function () {
    $teacher = staffOf($this->school, attributes: ['first_name' => 'Ngozi', 'last_name' => 'Mbakwe', 'department' => 'Sciences']);
    staffOf($this->school, SchoolRole::Bursar, ['first_name' => 'Grace', 'last_name' => 'Udofia', 'department' => 'Accounts']);
    staffOf($this->school, SchoolRole::StoreKeeper, ['first_name' => 'Tunde', 'last_name' => 'Bakare', 'department' => 'Accounts'])->update(['status' => 'on_leave']);
    inSchool($this->school, fn () => ClassArm::query()->firstOrFail()->update(['form_teacher_id' => $teacher->id]));
    $list = fn (array $query) => $this->actingAs($this->admin)->get(route('school.staff.index', [$this->school, ...$query]))->assertOk();

    $list([])->assertSee('Mbakwe')->assertSee('Udofia')->assertSee('Bakare')->assertSee('1 teaching, 2 support', false);
    $list(['type' => 'teaching'])->assertSee('Mbakwe')->assertDontSee('Udofia')->assertDontSee('Bakare');
    $list(['type' => 'support'])->assertDontSee('Mbakwe')->assertSee('Udofia')->assertSee('Bakare');
    $list(['department' => 'Accounts', 'status' => 'on_leave'])->assertDontSee('Udofia')->assertSee('Bakare')->assertSee('On leave');
});
