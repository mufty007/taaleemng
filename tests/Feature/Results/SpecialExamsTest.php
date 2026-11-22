<?php

use App\Enums\EnrolmentStatus;
use App\Enums\SchoolRole;
use App\Models\ClassArm;
use App\Models\SpecialExam;
use App\Models\SpecialExamCandidate;
use App\Models\SpecialExamPaper;
use App\Models\SpecialExamScore;
use App\Models\Subject;
use App\Support\Exams\SpecialExamResults;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->principal = memberOf($this->school, SchoolRole::Principal);

    $this->aisha = enrol($this->school, 'SSS 3', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello']);
    $this->chidi = enrol($this->school, 'SSS 3', 'A', ['first_name' => 'Chidi', 'last_name' => 'Okafor']);
    $this->tunde = enrol($this->school, 'SSS 3', 'A', ['first_name' => 'Tunde', 'last_name' => 'Ade']);
    enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Musa', 'last_name' => 'Yakubu']);

    inSchool($this->school, function () {
        $this->ss3a = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'SSS 3'))->where('name', 'A')->sole();
        $this->english = Subject::query()->where('code', 'ENG')->sole();
        $this->maths = Subject::query()->where('code', 'MTH')->sole();
    });

    $this->setExam = fn (array $overrides = []) => $this->actingAs($this->principal)->post(route('school.special-exams.store', $this->school), [
        'title' => 'SS 3 WAEC mock',
        'kind' => 'waec',
        'held_on' => '2026-11-10',
        'pass_mark' => 50,
        'max_score' => 100,
        'class_arm_ids' => [$this->ss3a->id],
        'subject_ids' => [$this->maths->id, $this->english->id],
        ...$overrides,
    ]);
    $this->exam = fn () => inSchool($this->school, fn () => SpecialExam::query()->with(['papers', 'candidates.student'])->sole());
    $this->candidate = fn (string $firstName) => ($this->exam)()->candidates->first(fn (SpecialExamCandidate $candidate) => $candidate->student->first_name === $firstName);
    $this->paper = fn (string $name) => ($this->exam)()->papers->firstWhere('name', $name);
});

it('sets an exam with a paper per subject and every student on roll in the chosen classes', function () {
    inSchool($this->school, fn () => $this->tunde->student->currentEnrolment->update(['status' => EnrolmentStatus::Withdrawn]));

    ($this->setExam)()->assertRedirect()->assertSessionHas('status', 'SS 3 WAEC mock is set for 2 candidates. Enter the marks as papers are marked.');

    $exam = ($this->exam)();

    expect($exam->kind->label())->toBe('WAEC practice')
        ->and($exam->papers->pluck('name')->all())->toBe(['English Language', 'Mathematics'])
        ->and($exam->papers->pluck('max_score')->unique()->all())->toBe([100])
        ->and($exam->candidates->pluck('student.first_name')->sort()->values()->all())->toBe(['Aisha', 'Chidi']);
});

it('asks for at least one class and one paper, from this school only', function () {
    $other = schoolWithStructure(['slug' => 'royal-crest']);
    $otherArm = inSchool($other, fn () => ClassArm::query()->firstOrFail());

    ($this->setExam)(['class_arm_ids' => [], 'subject_ids' => []])
        ->assertSessionHasErrorsIn('exam', ['class_arm_ids' => 'Choose at least one class to sit the exam.', 'subject_ids' => 'Choose at least one paper.']);
    ($this->setExam)(['class_arm_ids' => [$otherArm->id]])->assertSessionHasErrorsIn('exam', 'class_arm_ids.0');

    inSchool($this->school, fn () => expect(SpecialExam::query()->count())->toBe(0));
});

it('saves marks, clears emptied ones and refuses marks above the paper’s maximum', function () {
    ($this->setExam)();
    [$aisha, $maths, $english] = [($this->candidate)('Aisha'), ($this->paper)('Mathematics'), ($this->paper)('English Language')];
    $save = fn (array $scores) => $this->actingAs($this->principal)->put(route('school.special-exams.scores.update', [$this->school, ($this->exam)()]), ['scores' => $scores]);

    $save([$aisha->id => [$maths->id => '72.5', $english->id => '64']])->assertSessionHas('status', 'Marks saved.');
    $save([$aisha->id => [$maths->id => '', $english->id => '64']])->assertSessionHasNoErrors();
    $save([$aisha->id => [$english->id => '101']])->assertSessionHasErrors(["scores.{$aisha->id}.{$english->id}" => 'English Language is out of 100.']);

    inSchool($this->school, fn () => expect(SpecialExamScore::query()->pluck('score', 'special_exam_paper_id')->all())->toBe([$english->id => 64.0]));
});

it('refuses marks for students who are not sitting the exam', function () {
    ($this->setExam)();
    $maths = ($this->paper)('Mathematics');
    $mock = ($this->exam)();
    $elsewhere = inSchool($this->school, fn () => SpecialExamCandidate::query()->create(['special_exam_id' => SpecialExam::query()->create([
        'academic_session_id' => $mock->academic_session_id, 'title' => 'JSS 1 test', 'kind' => 'other', 'held_on' => '2026-11-11',
    ])->id, 'student_id' => $this->aisha->student->id]));

    $this->actingAs($this->principal)->put(route('school.special-exams.scores.update', [$this->school, $mock]), ['scores' => [$elsewhere->id => [$maths->id => '50']]])
        ->assertStatus(422);

    inSchool($this->school, fn () => expect(SpecialExamScore::query()->count())->toBe(0));
});

it('ranks candidates by total, shares tied positions and leaves out those with no marks', function () {
    ($this->setExam)();

    inSchool($this->school, function () {
        $marks = ['Aisha' => [70, 60], 'Chidi' => [40, 50]];

        foreach ($marks as $name => [$maths, $english]) {
            $candidate = ($this->candidate)($name);
            SpecialExamScore::query()->create(['special_exam_candidate_id' => $candidate->id, 'special_exam_paper_id' => ($this->paper)('Mathematics')->id, 'score' => $maths]);
            SpecialExamScore::query()->create(['special_exam_candidate_id' => $candidate->id, 'special_exam_paper_id' => ($this->paper)('English Language')->id, 'score' => $english]);
        }
    });

    $results = inSchool($this->school, fn () => SpecialExamResults::for(($this->exam)()));
    $rows = $results->candidates->keyBy(fn (array $row) => $row['student']->first_name);

    expect($results->maxTotal)->toBe(200)
        ->and($rows['Aisha'])->toMatchArray(['total' => 130.0, 'percent' => 65.0, 'passed' => true, 'position' => 1])
        ->and($rows['Chidi'])->toMatchArray(['total' => 90.0, 'percent' => 45.0, 'passed' => false, 'position' => 2])
        ->and($rows['Tunde'])->toMatchArray(['sat' => 0, 'percent' => null, 'passed' => null, 'position' => null])
        ->and($results->candidates->last()['student']->first_name)->toBe('Tunde')
        ->and($results->summary())->toMatchArray(['candidates' => 3, 'sat' => 2, 'average' => 55.0, 'pass_rate' => 50, 'highest' => 130.0])
        ->and($results->paperResults->first(fn (array $paper) => $paper['paper']->name === 'Mathematics'))->toMatchArray(['sat' => 2, 'average' => 55.0, 'pass_rate' => 50]);

    $this->actingAs($this->principal)->get(route('school.special-exams.show', [$this->school, ($this->exam)()]))
        ->assertOk()
        ->assertSeeInOrder(['BELLO Aisha', 'OKAFOR Chidi', 'ADE Tunde'])
        ->assertSee('130 / 200');
    $this->actingAs($this->principal)->get(route('school.special-exams.print', [$this->school, ($this->exam)()]))->assertOk()->assertSee('Exams officer');
    $this->actingAs($this->principal)->get(route('school.special-exams.slips', [$this->school, ($this->exam)()]))->assertOk()->assertSee('1st of 2')->assertDontSee('ADE Tunde');
});

it('shows results on the portal only once they are published', function () {
    ($this->setExam)();
    inSchool($this->school, fn () => SpecialExamScore::query()->create(['special_exam_candidate_id' => ($this->candidate)('Aisha')->id, 'special_exam_paper_id' => ($this->paper)('Mathematics')->id, 'score' => 81]));
    $portal = fn () => $this->actingAs($this->aisha->student->user)->get(route('school.portal', $this->school));

    $portal()->assertOk()->assertDontSee('Mock & practice exams');

    $this->actingAs($this->principal)->post(route('school.special-exams.publish', [$this->school, ($this->exam)()]))
        ->assertSessionHas('status', 'Results published. Students and parents can now see them on the portal.');

    $portal()->assertSee('Mock & practice exams')->assertSee('SS 3 WAEC mock')->assertSee('81');
    $this->actingAs($this->aisha->guardian->user)->get(route('school.portal.child', [$this->school, $this->aisha->student]))->assertOk()->assertSee('SS 3 WAEC mock');
});

it('keeps special exams to staff who manage academics, in their own school', function () {
    ($this->setExam)();
    $teacher = memberOf($this->school, SchoolRole::Teacher);
    $otherAdmin = memberOf(schoolWithStructure(['slug' => 'royal-crest']), SchoolRole::SchoolAdmin);

    $this->actingAs($teacher)->get(route('school.special-exams.index', $this->school))->assertForbidden();
    $this->actingAs($otherAdmin)->get('/app/royal-crest/special-exams/'.($this->exam)()->ulid)->assertNotFound();
});

it('deletes an exam with its candidates and marks', function () {
    ($this->setExam)();
    inSchool($this->school, fn () => SpecialExamScore::query()->create(['special_exam_candidate_id' => ($this->candidate)('Aisha')->id, 'special_exam_paper_id' => ($this->paper)('Mathematics')->id, 'score' => 81]));

    $this->actingAs($this->principal)->delete(route('school.special-exams.destroy', [$this->school, ($this->exam)()]))
        ->assertRedirect(route('school.special-exams.index', $this->school));

    inSchool($this->school, fn () => expect([SpecialExam::query()->count(), SpecialExamPaper::query()->count(), SpecialExamCandidate::query()->count(), SpecialExamScore::query()->count()])->toBe([0, 0, 0, 0]));
});
