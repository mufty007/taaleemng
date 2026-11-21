<?php

use App\Actions\Academic\GenerateExamTimetable;
use App\Enums\SchoolRole;
use App\Models\ClassLevel;
use App\Models\ExamPaper;
use App\Models\Subject;
use App\Models\Term;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->principal = memberOf($this->school, SchoolRole::Principal);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha']);
    $this->invigilator = staffOf($this->school);

    inSchool($this->school, function () {
        $this->term = Term::current();
        $this->jss1 = ClassLevel::query()->where('name', 'JSS 1')->sole();
        $this->maths = Subject::query()->where('code', 'MTH')->value('id');
        $this->english = Subject::query()->where('code', 'ENG')->value('id');
    });

    $this->monday = $this->term->ends_on->copy()->subWeeks(2)->startOfWeek();
    $this->add = fn (array $data) => $this->actingAs($this->principal)->post(route('school.exams.store', $this->school), [
        'term_id' => $this->term->id, 'class_level_id' => $this->jss1->id, 'subject_id' => $this->maths,
        'date' => $this->monday->toDateString(), 'time' => '09:00', 'duration_minutes' => 120, ...$data,
    ]);
});

it('drafts a timetable where no class sits two papers at once', function () {
    $result = inSchool($this->school, fn () => app(GenerateExamTimetable::class)->handle($this->term, $this->monday, ['08:30', '11:30']));

    expect($result['created'])->toBeGreaterThan(20);

    inSchool($this->school, function () {
        $jss1 = ExamPaper::query()->where('class_level_id', $this->jss1->id)->orderBy('starts_at')->get();
        expect($jss1->first()->subject_id)->toBeIn([$this->english, $this->maths])
            ->and($jss1->pluck('starts_at')->map->toDateTimeString()->duplicates())->toBeEmpty()
            ->and($jss1->every(fn (ExamPaper $paper) => ! $paper->starts_at->isWeekend()))->toBeTrue()
            ->and($jss1->pluck('subject_id')->duplicates())->toBeEmpty();
    });

    // Drafting again adds nothing new.
    expect(inSchool($this->school, fn () => app(GenerateExamTimetable::class)->handle($this->term, $this->monday, ['08:30']))['created'])->toBe(0);
});

it('refuses a clash for the class or the invigilator', function () {
    ($this->add)(['invigilator_id' => $this->invigilator->id])->assertSessionHasNoErrors();

    ($this->add)(['subject_id' => $this->english, 'time' => '10:00'])->assertSessionHasErrorsIn('paper', 'time');
    $jss2 = inSchool($this->school, fn () => ClassLevel::query()->where('name', 'JSS 2')->value('id'));
    ($this->add)(['class_level_id' => $jss2, 'time' => '10:30', 'invigilator_id' => $this->invigilator->id])->assertSessionHasErrorsIn('paper', 'invigilator_id');
    ($this->add)(['subject_id' => $this->english, 'time' => '11:00'])->assertSessionHasNoErrors();

    $this->actingAs(staffOf($this->school)->user)->post(route('school.exams.store', $this->school), [])->assertForbidden();
});

it('shows a student their papers and prints the timetable with the school’s letterhead', function () {
    ($this->add)(['venue' => 'Main hall', 'notes' => 'Bring a calculator']);

    $this->actingAs($this->aisha->student->user)->get(route('school.portal', $this->school))
        ->assertOk()->assertSee('Mathematics')->assertSee('Main hall · Bring a calculator');
    $this->actingAs($this->aisha->guardian->user)->get(route('school.portal.child', [$this->school, $this->aisha->student]))->assertSee('Main hall');

    $this->actingAs($this->invigilator->user)->get(route('school.exams.index', $this->school))->assertOk()->assertSee('Mathematics')->assertDontSee('Draft the timetable');
    $this->actingAs($this->principal)->get(route('school.exams.print', [$this->school, 'level' => $this->jss1->id]))
        ->assertOk()->assertSee('Exam timetable')->assertSee($this->monday->format('l j F Y'))->assertSee('Powered by');
});
