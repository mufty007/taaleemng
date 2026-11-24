<?php

use App\Enums\Plan;
use App\Enums\SchoolRole;
use App\Models\ClassArm;
use App\Models\Staff;

beforeEach(function () {
    config([
        'taaleem.plans.basic.max_students' => 2,
        'taaleem.plans.basic.max_staff' => 2,
    ]);

    $this->school = schoolWithStructure(['slug' => 'limits', 'plan' => Plan::Basic]);
    $this->admin = memberOf($this->school, SchoolRole::SchoolAdmin);
    $this->arm = inSchool($this->school, fn () => ClassArm::query()->first());
});

it('blocks enrolling a student once the plan’s student seats are full', function () {
    enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Ada']);
    enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Bola']);

    $this->actingAs($this->admin)->post(route('school.students.store', $this->school), [
        'first_name' => 'Chioma',
        'last_name' => 'Okafor',
        'gender' => 'female',
        'class_arm_id' => $this->arm->id,
        'guardian_first_name' => 'Ngozi',
        'guardian_last_name' => 'Okafor',
        'guardian_relationship' => 'mother',
        'guardian_phone' => '0803 111 2223',
    ])->assertSessionHasErrors('plan');
});

it('blocks adding staff once the plan’s staff seats are full', function () {
    staffOf($this->school);
    staffOf($this->school);

    expect(inSchool($this->school, fn () => Staff::query()->count()))->toBe(2);

    $this->actingAs($this->admin)->post(route('school.staff.store', $this->school), [
        'first_name' => 'Tunde',
        'last_name' => 'Bakare',
        'gender' => 'male',
        'designation' => 'Teacher',
        'employment_type' => 'full_time',
        'role' => SchoolRole::Teacher->value,
        'email' => 'tunde.extra@limits.test',
    ])->assertSessionHasErrors('plan');
});

it('tells the admin why a student was not added, with a way to see the plans', function () {
    enrol($this->school, 'JSS 1', 'A');
    enrol($this->school, 'JSS 1', 'A');

    $this->actingAs($this->admin)
        ->from(route('school.students.create', $this->school))
        ->followingRedirects()
        ->post(route('school.students.store', $this->school), [
            'first_name' => 'Chioma',
            'last_name' => 'Okafor',
            'gender' => 'female',
            'class_arm_id' => $this->arm->id,
            'guardian_first_name' => 'Ngozi',
            'guardian_last_name' => 'Okafor',
            'guardian_relationship' => 'mother',
            'guardian_phone' => '0803 111 2224',
        ])
        ->assertOk()
        ->assertSee('plan allows 2 students')
        ->assertSee('See plans');
});
