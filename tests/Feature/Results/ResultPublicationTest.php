<?php

use App\Enums\SchoolRole;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ResultPublication;
use App\Models\Score;
use App\Models\Term;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->enrolled = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello']);
    $this->student = $this->enrolled->student;
    $this->parent = $this->enrolled->guardian->user;

    inSchool($this->school, function () {
        $this->arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->term = Term::current();
        $classSubject = $this->arm->classSubjects()->first();

        foreach (AssessmentComponent::query()->get() as $component) {
            Score::query()->create([
                'term_id' => $this->term->id,
                'enrolment_id' => $this->student->currentEnrolment->id,
                'class_subject_id' => $classSubject->id,
                'assessment_component_id' => $component->id,
                'score' => $component->max_score - 5,
            ]);
        }
    });

    $this->reportCard = route('school.results.report', [$this->school, $this->student]);
});

it('hides results from families until the class is published', function () {
    $this->actingAs($this->parent)->get($this->reportCard)->assertNotFound();
    $this->actingAs($this->student->user)->get($this->reportCard)->assertNotFound();
    $this->actingAs($this->parent)->get(route('school.portal.child', [$this->school, $this->student]))->assertOk()->assertSee('No results yet');

    $this->actingAs(memberOf($this->school))->post(route('school.results.publish', [$this->school, $this->arm]))->assertRedirect();

    $this->actingAs($this->parent)->get($this->reportCard)->assertOk()->assertSee('Aisha')->assertSee('First Term');
    $this->actingAs($this->student->user)->get($this->reportCard)->assertOk();
    $this->actingAs($this->parent)->get(route('school.portal.child', [$this->school, $this->student]))->assertDontSee('No results yet')->assertSee($this->reportCard);
});

it('withdraws a publication on the second press and logs both', function () {
    $admin = memberOf($this->school);

    $this->actingAs($admin)->post(route('school.results.publish', [$this->school, $this->arm]));
    $this->actingAs($admin)->post(route('school.results.publish', [$this->school, $this->arm]));

    inSchool($this->school, fn () => expect(ResultPublication::query()->count())->toBe(0));
    $this->actingAs($this->parent)->get($this->reportCard)->assertNotFound();
    $this->assertDatabaseHas('activity_log', ['school_id' => $this->school->id, 'description' => "published JSS 1A {$this->term->name} results"]);
    $this->assertDatabaseHas('activity_log', ['school_id' => $this->school->id, 'description' => "withdrew JSS 1A {$this->term->name} results"]);
});

it('lets staff see an unpublished report card', function () {
    $this->actingAs(memberOf($this->school))->get($this->reportCard)->assertOk()->assertSee('Aisha');
});

it('keeps another family away from the report card', function () {
    $stranger = enrol($this->school, 'JSS 1', 'A')->guardian->user;
    $this->actingAs(memberOf($this->school))->post(route('school.results.publish', [$this->school, $this->arm]));

    $this->actingAs($stranger)->get($this->reportCard)->assertForbidden();
});

it('shows a teacher the broadsheet of their own class only', function () {
    $teacher = staffOf($this->school);
    inSchool($this->school, fn () => $this->arm->classSubjects()->first()->update(['teacher_id' => $teacher->id]));
    $otherArm = inSchool($this->school, fn () => ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'SSS 1'))->where('name', 'A')->sole());

    $this->actingAs($teacher->user)->get(route('school.results.show', [$this->school, $this->arm]))->assertOk()->assertSee('BELLO Aisha');
    $this->actingAs($teacher->user)->get(route('school.results.show', [$this->school, $otherArm]))->assertForbidden();
    $this->actingAs($teacher->user)->post(route('school.results.publish', [$this->school, $this->arm]))->assertForbidden();
});

it('only lets someone with the publish permission release results', function () {
    $this->actingAs(memberOf($this->school, SchoolRole::Bursar))->post(route('school.results.publish', [$this->school, $this->arm]))->assertForbidden();
    $this->actingAs($this->parent)->post(route('school.results.publish', [$this->school, $this->arm]))->assertForbidden();
});
