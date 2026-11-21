<?php

use App\Enums\SchoolRole;
use App\Models\ClassArm;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->mine = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Taught']);
    $this->other = enrol($this->school, 'SSS 3', 'A', ['first_name' => 'Elsewhere']);

    $this->teacher = staffOf($this->school);
    inSchool($this->school, fn () => ClassArm::query()
        ->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole()
        ->classSubjects()->first()->update(['teacher_id' => $this->teacher->id]));
});

it('shows a teacher only the students in their classes', function () {
    $this->actingAs($this->teacher->user)->get(route('school.students.index', $this->school))
        ->assertOk()
        ->assertSee('Taught')
        ->assertDontSee('Elsewhere');

    $this->actingAs($this->teacher->user)->get(route('school.students.show', [$this->school, $this->mine->student]))->assertOk();
    $this->actingAs($this->teacher->user)->get(route('school.students.show', [$this->school, $this->other->student]))->assertForbidden();
});

it('shows the bursar every student', function () {
    $bursar = memberOf($this->school, SchoolRole::Bursar);

    $this->actingAs($bursar)->get(route('school.students.index', $this->school))->assertSee('Taught')->assertSee('Elsewhere');
    $this->actingAs($bursar)->get(route('school.students.show', [$this->school, $this->other->student]))->assertOk();
});

it('shows a parent their own children and nobody else’s', function () {
    $parent = $this->mine->guardian->user;

    $this->actingAs($parent)->get(route('school.dashboard', $this->school))->assertRedirect(route('school.portal', $this->school));
    $this->actingAs($parent)->get(route('school.portal', $this->school))->assertOk()->assertSee('Taught')->assertDontSee('Elsewhere');
    $this->actingAs($parent)->get(route('school.portal.child', [$this->school, $this->mine->student]))->assertOk();
    $this->actingAs($parent)->get(route('school.portal.child', [$this->school, $this->other->student]))->assertForbidden();
    $this->actingAs($parent)->get(route('school.students.index', $this->school))->assertForbidden();
    $this->actingAs($parent)->get(route('school.classes.index', $this->school))->assertForbidden();
});

it('shows a student their own class and subjects', function () {
    $student = $this->mine->student->user;

    $this->actingAs($student)->get(route('school.portal', $this->school))
        ->assertOk()
        ->assertSee('JSS 1A')
        ->assertSee('Mathematics');

    $this->actingAs($student)->get(route('school.students.show', [$this->school, $this->other->student]))->assertForbidden();
    $this->actingAs($student)->get(route('school.portal.child', [$this->school, $this->mine->student]))->assertNotFound();
});

it('lets a parent of children in two schools see each school’s child separately', function () {
    $otherSchool = schoolWithStructure(['slug' => 'al-noor']);
    $phone = $this->mine->guardian->phone;
    $abroad = enrol($otherSchool, 'Primary 2', 'A', ['first_name' => 'Maryam'], ['phone' => $phone]);
    $parent = $this->mine->guardian->user;

    $this->actingAs($parent)->get(route('app'))->assertRedirect(route('schools.pick'));
    $this->actingAs($parent)->get(route('school.portal', $otherSchool))->assertOk()->assertSee('Maryam')->assertDontSee('Taught');
    $this->actingAs($parent)->get(route('school.portal.child', [$otherSchool, $abroad->student]))->assertOk();
    $this->actingAs($parent)->get('/app/al-noor/portal/children/'.$this->mine->student->ulid)->assertNotFound();
});
