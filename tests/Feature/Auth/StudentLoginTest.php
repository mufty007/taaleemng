<?php

use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 10, 5));
    $this->school = schoolWithStructure(['short_code' => 'GFA', 'slug' => 'greenfield']);
    $this->result = enrol($this->school, 'JSS 2', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello']);
});

it('logs a student in with the school code and admission number', function () {
    $this->post(route('login.store'), ['school_code' => 'gfa', 'login' => 'gfa/2026/0001', 'password' => 'password'])
        ->assertRedirect('/app');

    $this->assertAuthenticatedAs($this->result->student->user);

    $this->get('/app')->assertRedirect('/app/greenfield');
    $this->get('/app/greenfield')->assertRedirect('/app/greenfield/portal');
    $this->get('/app/greenfield/portal')->assertOk()->assertSee('Aisha')->assertSee('JSS 2A');
});

it('rejects the right admission number with the wrong school code', function () {
    $otherSchool = schoolWithStructure(['short_code' => 'ANM']);

    $this->post(route('login.store'), ['school_code' => 'ANM', 'login' => 'GFA/2026/0001', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->post(route('login.store'), ['school_code' => 'XYZ', 'login' => 'GFA/2026/0001', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('logs the parent in with the phone number used at enrolment', function () {
    $this->post(route('login.store'), ['login' => $this->result->guardian->phone, 'password' => 'password'])->assertRedirect('/app');

    $this->assertAuthenticatedAs($this->result->guardian->user);
    $this->get('/app/greenfield')->assertRedirect('/app/greenfield/portal');
});

it('shows the student tab on the login page', function () {
    $this->get(route('login'))->assertOk()->assertSee('School code')->assertSee('Admission number');
});
