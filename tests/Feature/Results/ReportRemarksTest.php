<?php

use App\Enums\PromotionDecision;
use App\Enums\SchoolRole;
use App\Enums\TermStatus;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ReportTrait;
use App\Models\Score;
use App\Models\Term;
use App\Models\TermReport;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->formTeacher = staffOf($this->school);
    $this->enrolled = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello']);
    $this->student = $this->enrolled->student;

    inSchool($this->school, function () {
        $this->arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->arm->update(['form_teacher_id' => $this->formTeacher->id]);
        $this->enrolment = $this->student->currentEnrolment;
        $this->terms = Term::query()->orderBy('sequence')->get()->keyBy('sequence');
        $this->terms[1]->update(['school_days' => 60]);
        $this->traits = ReportTrait::query()->pluck('id');
    });

    /** Give the student the same total in every subject they take, for a term. */
    $this->scoreTerm = fn (Term $term, array $scores) => inSchool($this->school, function () use ($term, $scores) {
        $components = AssessmentComponent::query()->orderBy('position')->pluck('id')->all();

        foreach ($this->arm->classSubjects()->limit(2)->get() as $classSubject) {
            foreach ($scores as $index => $score) {
                Score::query()->create([
                    'term_id' => $term->id,
                    'enrolment_id' => $this->enrolment->id,
                    'class_subject_id' => $classSubject->id,
                    'assessment_component_id' => $components[$index],
                    'score' => $score,
                ]);
            }
        }
    });

    $this->saveRemarks = fn ($user, array $data, ?Term $term = null) => $this->actingAs($user)->putJson(
        route('school.remarks.update', [$this->school, $this->arm, $this->enrolment, 'term' => ($term ?? $this->terms[1])->id]),
        $data,
    );
});

it('lets the form teacher record attendance, ratings and a comment', function () {
    ($this->scoreTerm)($this->terms[1], [15, 15, 40]);

    $this->actingAs($this->formTeacher->user)->get(route('school.remarks.edit', [$this->school, $this->arm]))
        ->assertOk()
        ->assertSee('BELLO Aisha')
        ->assertSee('Aisha is diligent', escape: false);

    ($this->saveRemarks)($this->formTeacher->user, [
        'days_present' => 57,
        'traits' => $this->traits->mapWithKeys(fn ($id) => [$id => 4])->all(),
        'teacher_comment' => 'A steady term. Keep reading every evening.',
        'principal_comment' => 'Sneaked in by the form teacher',
        'promotion_decision' => 'promoted',
    ])->assertOk()->assertJson(['complete' => true]);

    inSchool($this->school, function () {
        $report = TermReport::query()->sole();

        expect($report->days_present)->toBe(57)
            ->and($report->traits)->toHaveCount($this->traits->count())
            ->and($report->teacher_comment)->toBe('A steady term. Keep reading every evening.')
            ->and($report->principal_comment)->toBeNull()
            ->and($report->promotion_decision)->toBeNull()
            ->and($report->updated_by)->toBe($this->formTeacher->user_id);
    });
});

it('marks a report incomplete until every trait is rated', function () {
    ($this->saveRemarks)($this->formTeacher->user, [
        'days_present' => 50,
        'traits' => [$this->traits->first() => 5],
        'teacher_comment' => 'Good.',
    ])->assertOk()->assertJson(['complete' => false]);
});

it('rejects more days present than the school opened, and ratings outside 1 to 5', function () {
    ($this->saveRemarks)($this->formTeacher->user, ['days_present' => 61])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['days_present' => 'The school only opened 60 times this term.']);

    ($this->saveRemarks)($this->formTeacher->user, ['traits' => [$this->traits->first() => 6]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('traits.'.$this->traits->first());
});

it('keeps other teachers, parents and students out', function () {
    $otherTeacher = staffOf($this->school);
    inSchool($this->school, fn () => $this->arm->classSubjects()->first()->update(['teacher_id' => $otherTeacher->id]));

    $this->actingAs($otherTeacher->user)->get(route('school.remarks.edit', [$this->school, $this->arm]))->assertForbidden();
    ($this->saveRemarks)($otherTeacher->user, ['teacher_comment' => 'Not my class'])->assertForbidden();
    ($this->saveRemarks)($this->enrolled->guardian->user, ['teacher_comment' => 'Lovely'])->assertForbidden();
    ($this->saveRemarks)($this->student->user, ['teacher_comment' => 'Brilliant'])->assertForbidden();
});

it('refuses a student from another class', function () {
    $stranger = enrol($this->school, 'JSS 2', 'A')->student;
    $strangerEnrolment = inSchool($this->school, fn () => $stranger->currentEnrolment);

    $this->actingAs($this->formTeacher->user)
        ->putJson(route('school.remarks.update', [$this->school, $this->arm, $strangerEnrolment]), ['teacher_comment' => 'Hi'])
        ->assertNotFound();
});

it('locks remarks once the term is closed', function () {
    inSchool($this->school, fn () => $this->terms[1]->update(['status' => TermStatus::Closed]));

    ($this->saveRemarks)($this->formTeacher->user, ['teacher_comment' => 'Late'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('term');
});

it('lets the principal comment, and decide promotion only in the third term', function () {
    $principal = memberOf($this->school, SchoolRole::Principal);

    ($this->saveRemarks)($principal, ['principal_comment' => 'Well done.', 'promotion_decision' => 'promoted'])->assertOk();
    inSchool($this->school, fn () => expect(TermReport::query()->sole())
        ->principal_comment->toBe('Well done.')
        ->promotion_decision->toBeNull());

    ($this->saveRemarks)($principal, ['promotion_decision' => 'repeat'], $this->terms[3])->assertOk();
    inSchool($this->school, fn () => expect(TermReport::query()->where('term_id', $this->terms[3]->id)->sole()->promotion_decision)->toBe(PromotionDecision::Repeat));
});

it('fills only empty comments from the comment bank', function () {
    $other = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Chidi']);
    ($this->scoreTerm)($this->terms[1], [18, 18, 50]);
    ($this->saveRemarks)($this->formTeacher->user, ['teacher_comment' => 'My own words.'])->assertOk();

    $this->actingAs($this->formTeacher->user)
        ->post(route('school.remarks.autofill', [$this->school, $this->arm]))
        ->assertRedirect()
        ->assertSessionHas('status', 'No empty comments to fill.');

    inSchool($this->school, function () use ($other) {
        expect(TermReport::query()->sole()->teacher_comment)->toBe('My own words.')
            ->and(TermReport::query()->where('enrolment_id', $other->student->currentEnrolment->id)->exists())->toBeFalse();
    });

    $principal = memberOf($this->school, SchoolRole::Principal);
    $this->actingAs($principal)->post(route('school.remarks.autofill', [$this->school, $this->arm]))->assertSessionHas('status');

    inSchool($this->school, fn () => expect(TermReport::query()->sole()->principal_comment)->toBeIn(['An excellent result. We are proud of Aisha.', 'Outstanding performance. Keep soaring, Aisha.']));
});

it('prints remarks, attendance and the session result on the third-term report card', function () {
    ($this->scoreTerm)($this->terms[1], [10, 10, 30]);
    ($this->scoreTerm)($this->terms[2], [15, 15, 40]);
    ($this->scoreTerm)($this->terms[3], [20, 20, 50]);

    inSchool($this->school, function () {
        $this->terms[3]->update(['school_days' => 58]);
        TermReport::query()->create([
            'term_id' => $this->terms[3]->id,
            'enrolment_id' => $this->enrolment->id,
            'days_present' => 55,
            'traits' => [$this->traits->first() => 5],
            'teacher_comment' => 'A wonderful finish to the year.',
            'principal_comment' => 'Promoted with distinction.',
            'promotion_decision' => PromotionDecision::Promoted,
        ]);
    });

    $this->actingAs(memberOf($this->school))->get(route('school.results.report', [$this->school, $this->student, 'term' => $this->terms[3]->id]))
        ->assertOk()
        ->assertSeeInOrder(['Times school opened', '58', 'Times present', '55', 'Times absent', '3'])
        ->assertSee('5 · Excellent')
        ->assertSee('A wonderful finish to the year.')
        ->assertSee('Promoted with distinction.')
        ->assertSee('Promoted to JSS 2')
        ->assertSee('Cum. avg.')
        ->assertSeeInOrder(['Session average', '70'])
        ->assertSeeInOrder(['50', '70', '90']);
});

it('waits for all of a student’s scores before suggesting a comment', function () {
    ($this->scoreTerm)($this->terms[1], [15, 15]);

    $this->actingAs($this->formTeacher->user)->get(route('school.remarks.edit', [$this->school, $this->arm]))
        ->assertOk()
        ->assertDontSee('Aisha needs to work much harder', escape: false);

    $this->actingAs($this->formTeacher->user)
        ->post(route('school.remarks.autofill', [$this->school, $this->arm]))
        ->assertSessionHas('status', 'No empty comments to fill. 1 student skipped until all their scores are in.');

    inSchool($this->school, fn () => expect(TermReport::query()->count())->toBe(0));
});
