<?php

use App\Enums\SchoolRole;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->admin = memberOf($this->school, SchoolRole::SchoolAdmin);
    $this->principal = memberOf($this->school, SchoolRole::Principal);
    $this->head = memberOf($this->school, SchoolRole::HeadTeacher);
});

it('lets school admins open the reports hub and each report', function () {
    $this->actingAs($this->admin)->get(route('school.reports.index', $this->school))
        ->assertOk()
        ->assertSee('Enrolment by class')
        ->assertSee('Fees & debtors');

    foreach (['enrolment', 'fees', 'attendance', 'staff'] as $report) {
        $this->actingAs($this->admin)->get(route('school.reports.show', [$this->school, $report]))->assertOk();
    }
});

it('keeps reports away from principals and head teachers', function () {
    $this->actingAs($this->principal)->get(route('school.reports.index', $this->school))->assertForbidden();
    $this->actingAs($this->head)->get(route('school.reports.index', $this->school))->assertForbidden();
});
