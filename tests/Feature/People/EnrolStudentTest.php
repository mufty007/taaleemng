<?php

use App\Actions\Academic\ApplyNigerianDefaults;
use App\Enums\SchoolRole;
use App\Models\ClassArm;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 10, 5));
    $this->school = schoolWithStructure(['short_code' => 'GFA', 'slug' => 'greenfield']);
    $this->admin = memberOf($this->school);
    $this->jss1a = inSchool($this->school, fn () => ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole());
});

function enrolmentForm(array $overrides = []): array
{
    return [
        'first_name' => 'Aisha',
        'last_name' => 'Bello',
        'gender' => 'female',
        'date_of_birth' => '2014-03-14',
        'state_of_origin' => 'Kano',
        'religion' => 'Islam',
        'guardian_title' => 'Hajiya',
        'guardian_first_name' => 'Amina',
        'guardian_last_name' => 'Bello',
        'guardian_relationship' => 'mother',
        'guardian_phone' => '0802 123 4567',
        ...$overrides,
    ];
}

it('enrols a student with an admission number, a class and logins for student and parent', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('school.students.store', $this->school), enrolmentForm(['class_arm_id' => $this->jss1a->id]));

    $student = inSchool($this->school, fn () => Student::query()->with(['currentEnrolment', 'guardians.user', 'user'])->sole());

    $response->assertRedirect(route('school.students.show', [$this->school, $student]))
        ->assertSessionHas('credentials', fn (array $credentials) => count($credentials) === 2 && $credentials[0]['login'] === 'GFA/2026/0001');

    expect($student->admission_no)->toBe('GFA/2026/0001')
        ->and($student->currentEnrolment->class_arm_id)->toBe($this->jss1a->id)
        ->and($student->guardians->sole()->phone)->toBe('+2348021234567')
        ->and((bool) $student->guardians->sole()->pivot->is_primary)->toBeTrue()
        ->and($student->user)->not->toBeNull()
        ->and($student->guardians->sole()->user->phone)->toBe('+2348021234567');

    inSchool($this->school, function () use ($student) {
        expect($student->user->hasRole(SchoolRole::Student))->toBeTrue()
            ->and($student->guardians->sole()->user->hasRole(SchoolRole::Parent))->toBeTrue();
    });
});

it('links a second child to the same parent account by phone number', function () {
    $this->actingAs($this->admin)->post(route('school.students.store', $this->school), enrolmentForm(['class_arm_id' => $this->jss1a->id]));

    $this->actingAs($this->admin)
        ->post(route('school.students.store', $this->school), enrolmentForm(['first_name' => 'Fatima', 'class_arm_id' => $this->jss1a->id, 'guardian_phone' => '+234 802 123 4567']))
        ->assertSessionHas('credentials', fn (array $credentials) => count($credentials) === 1);

    inSchool($this->school, function () {
        expect(Guardian::query()->count())->toBe(1)
            ->and(Guardian::query()->sole()->students()->count())->toBe(2);
    });
    expect(User::query()->where('phone', '+2348021234567')->count())->toBe(1);
});

it('reuses a parent’s account from another school', function () {
    $otherSchool = schoolWithStructure();
    enrol($otherSchool, guardian: ['phone' => '+2348021234567']);

    $this->actingAs($this->admin)
        ->post(route('school.students.store', $this->school), enrolmentForm(['class_arm_id' => $this->jss1a->id]))
        ->assertSessionHas('credentials', fn (array $credentials) => count($credentials) === 1);

    $parent = User::query()->where('phone', '+2348021234567')->sole();

    expect($parent->activeSchools()->count())->toBe(2);
});

it('validates the enrolment form', function () {
    $this->actingAs($this->admin)
        ->post(route('school.students.store', $this->school), enrolmentForm(['class_arm_id' => 999999, 'guardian_phone' => '12345', 'gender' => 'other']))
        ->assertSessionHasErrors(['class_arm_id', 'guardian_phone', 'gender']);

    expect(inSchool($this->school, fn () => Student::query()->count()))->toBe(0);
});

it('needs a current session before enrolling', function () {
    $school = schoolWithRoles();
    inSchool($school, fn () => app(ApplyNigerianDefaults::class)->handle());
    $arm = inSchool($school, fn () => ClassArm::query()->first());

    $this->actingAs(memberOf($school))
        ->post(route('school.students.store', $school), enrolmentForm(['class_arm_id' => $arm->id]))
        ->assertSessionHasErrors('class_arm_id');
});

it('updates a student and resets their login', function () {
    $result = enrol($this->school);

    $this->actingAs($this->admin)
        ->put(route('school.students.update', [$this->school, $result->student]), [...enrolmentForm(), 'first_name' => 'Zainab', 'status' => 'active', 'medical_notes' => 'Asthmatic'])
        ->assertRedirect();

    $student = inSchool($this->school, fn () => $result->student->fresh());
    expect($student->first_name)->toBe('Zainab')->and($student->medical_notes)->toBe('Asthmatic');

    $this->actingAs($this->admin)
        ->post(route('school.students.password', [$this->school, $student]))
        ->assertSessionHas('credentials', fn (array $credentials) => $credentials[0]['login'] === $student->admission_no);
});

it('only lets admins enrol and edit students', function () {
    $teacher = memberOf($this->school, SchoolRole::Teacher);
    $result = enrol($this->school);

    $this->actingAs($teacher)->get(route('school.students.create', $this->school))->assertForbidden();
    $this->actingAs($teacher)->post(route('school.students.store', $this->school), enrolmentForm(['class_arm_id' => $this->jss1a->id]))->assertForbidden();
    $this->actingAs($teacher)->get(route('school.students.edit', [$this->school, $result->student]))->assertForbidden();
});
