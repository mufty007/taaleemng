<?php

use App\Enums\SchoolRole;
use App\Enums\TermStatus;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\Score;
use App\Models\Subject;
use App\Models\Term;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->teacher = staffOf($this->school);
    $this->pupil = enrol($this->school, 'JSS 1', 'A')->student;
    $this->elsewhere = enrol($this->school, 'JSS 2', 'A')->student;

    inSchool($this->school, function () {
        $arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->maths = $arm->classSubjects()->where('subject_id', Subject::query()->where('code', 'MTH')->value('id'))->sole();
        $this->maths->update(['teacher_id' => $this->teacher->id]);
        $this->english = $arm->classSubjects()->where('subject_id', Subject::query()->where('code', 'ENG')->value('id'))->sole();
        $this->components = AssessmentComponent::query()->orderBy('position')->pluck('id', 'short_name');
        $this->enrolmentId = $this->pupil->currentEnrolment->id;
        $this->foreignEnrolmentId = $this->elsewhere->currentEnrolment->id;
    });

    $this->save = fn ($user, $classSubject, array $cells) => $this->actingAs($user)
        ->putJson(route('school.scores.update', [$this->school, $classSubject]), ['scores' => $cells]);
});

it('saves a teacher’s scores for their own subject', function () {
    ($this->save)($this->teacher->user, $this->maths, [
        ['enrolment' => $this->enrolmentId, 'component' => $this->components['CA1'], 'score' => 18.5],
        ['enrolment' => $this->enrolmentId, 'component' => $this->components['Exam'], 'score' => '55'],
    ])->assertOk()->assertJson(['saved' => 2]);

    inSchool($this->school, function () {
        expect(Score::query()->where('class_subject_id', $this->maths->id)->orderBy('assessment_component_id')->pluck('score')->all())->toBe([18.5, 55.0])
            ->and(Score::query()->value('entered_by'))->toBe($this->teacher->user_id);
    });
});

it('updates a cell in place and clears it when emptied', function () {
    $cell = ['enrolment' => $this->enrolmentId, 'component' => $this->components['CA2']];

    ($this->save)($this->teacher->user, $this->maths, [[...$cell, 'score' => 10]])->assertOk();
    ($this->save)($this->teacher->user, $this->maths, [[...$cell, 'score' => 12]])->assertOk();
    inSchool($this->school, fn () => expect(Score::query()->pluck('score')->all())->toBe([12.0]));

    ($this->save)($this->teacher->user, $this->maths, [[...$cell, 'score' => null]])->assertOk();
    inSchool($this->school, fn () => expect(Score::query()->count())->toBe(0));
});

it('rejects a score above the component’s maximum', function () {
    ($this->save)($this->teacher->user, $this->maths, [
        ['enrolment' => $this->enrolmentId, 'component' => $this->components['CA1'], 'score' => 21],
    ])->assertUnprocessable()->assertJsonValidationErrors('scores.0.score');

    inSchool($this->school, fn () => expect(Score::query()->count())->toBe(0));
});

it('rejects a student who is not in the class', function () {
    ($this->save)($this->teacher->user, $this->maths, [
        ['enrolment' => $this->foreignEnrolmentId, 'component' => $this->components['CA1'], 'score' => 10],
    ])->assertUnprocessable()->assertJsonValidationErrors('scores.0.enrolment');
});

it('keeps a teacher out of subjects they do not teach', function () {
    $this->actingAs($this->teacher->user)->get(route('school.scores.edit', [$this->school, $this->english]))->assertForbidden();

    ($this->save)($this->teacher->user, $this->english, [
        ['enrolment' => $this->enrolmentId, 'component' => $this->components['CA1'], 'score' => 10],
    ])->assertForbidden();
});

it('lists only the teacher’s own subjects, and every subject for an admin', function () {
    $this->actingAs($this->teacher->user)->get(route('school.scores.index', $this->school))
        ->assertOk()
        ->assertSee(route('school.scores.edit', [$this->school, $this->maths]))
        ->assertDontSee(route('school.scores.edit', [$this->school, $this->english]));

    $admin = memberOf($this->school);
    $this->actingAs($admin)->get(route('school.scores.index', $this->school))->assertSee(route('school.scores.edit', [$this->school, $this->english]));
    $this->actingAs($admin)->get(route('school.scores.edit', [$this->school, $this->english]))->assertOk()->assertSee($this->pupil->registerName());
});

it('locks scores once the term is closed', function () {
    inSchool($this->school, fn () => Term::current()->update(['status' => TermStatus::Closed]));

    ($this->save)($this->teacher->user, $this->maths, [
        ['enrolment' => $this->enrolmentId, 'component' => $this->components['CA1'], 'score' => 10],
    ])->assertUnprocessable()->assertJsonValidationErrors('scores');
});

it('keeps families and the bursar out of the scorebook', function () {
    $this->actingAs(memberOf($this->school, SchoolRole::Bursar))->get(route('school.scores.index', $this->school))->assertForbidden();
    $this->actingAs($this->pupil->user)->get(route('school.scores.edit', [$this->school, $this->maths]))->assertForbidden();
});
