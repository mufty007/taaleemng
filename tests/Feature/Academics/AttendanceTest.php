<?php

use App\Enums\AttendanceStatus;
use App\Enums\SchoolRole;
use App\Enums\TermStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\Term;
use App\Support\Attendance\AttendanceSummary;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->formTeacher = staffOf($this->school);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'])->student;
    $this->chidi = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Chidi', 'last_name' => 'Okafor'])->student;

    inSchool($this->school, function () {
        $this->arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->arm->update(['form_teacher_id' => $this->formTeacher->id]);
        $this->term = Term::current();
        $this->day = $this->term->starts_on->copy()->next(Carbon::TUESDAY);
        $this->enrolments = [$this->aisha->currentEnrolment->id, $this->chidi->currentEnrolment->id];
    });

    $this->travelTo($this->day->copy()->setTime(9, 0));

    $this->save = fn ($user, array $records, ?Carbon $date = null) => $this->actingAs($user)->putJson(route('school.attendance.save', [$this->school, $this->arm]), [
        'date' => ($date ?? $this->day)->toDateString(),
        'records' => $records,
    ]);
});

it('lets the form teacher take the register and totals it for the term', function () {
    $this->actingAs($this->formTeacher->user)->get(route('school.attendance.index', $this->school))
        ->assertRedirect(route('school.attendance.register', [$this->school, $this->arm, 'date' => $this->day->toDateString()]));

    $this->actingAs($this->formTeacher->user)->get(route('school.attendance.register', [$this->school, $this->arm]))->assertOk()->assertSee('BELLO Aisha');

    ($this->save)($this->formTeacher->user, [
        ['enrolment' => $this->enrolments[0], 'status' => 'present'],
        ['enrolment' => $this->enrolments[1], 'status' => 'absent', 'note' => 'Malaria'],
    ])->assertOk()->assertJson(['saved' => 2]);

    ($this->save)($this->formTeacher->user, [['enrolment' => $this->enrolments[1], 'status' => 'late']])->assertOk();

    inSchool($this->school, function () {
        $summary = AttendanceSummary::forTerm($this->term, $this->enrolments);

        expect(AttendanceRecord::query()->count())->toBe(2)
            ->and($summary[$this->enrolments[0]])->toMatchArray(['days' => 1, 'present' => 1, 'rate' => 100])
            ->and($summary[$this->enrolments[1]])->toMatchArray(['days' => 1, 'late' => 1, 'absent' => 0, 'rate' => 100])
            ->and(AttendanceSummary::daysTaken($this->term, $this->arm->id))->toBe(1);
    });
});

it('refuses weekends, future days and closed terms', function () {
    ($this->save)($this->formTeacher->user, [['enrolment' => $this->enrolments[0], 'status' => 'present']], $this->day->copy()->next(Carbon::SATURDAY))->assertUnprocessable();
    ($this->save)($this->formTeacher->user, [['enrolment' => $this->enrolments[0], 'status' => 'present']], $this->day->copy()->addDay())->assertUnprocessable();

    inSchool($this->school, fn () => $this->term->update(['status' => TermStatus::Closed]));
    ($this->save)($this->formTeacher->user, [['enrolment' => $this->enrolments[0], 'status' => 'present']])->assertUnprocessable()->assertJsonValidationErrors('date');
});

it('keeps other teachers out of a class register, and other classes out of it', function () {
    $other = staffOf($this->school);
    $stranger = enrol($this->school, 'JSS 2', 'A')->student;
    $strangerEnrolment = inSchool($this->school, fn () => $stranger->currentEnrolment->id);

    $this->actingAs($other->user)->get(route('school.attendance.register', [$this->school, $this->arm]))->assertForbidden();
    ($this->save)($other->user, [['enrolment' => $this->enrolments[0], 'status' => 'present']])->assertForbidden();
    ($this->save)($this->formTeacher->user, [['enrolment' => $strangerEnrolment, 'status' => 'present']])->assertUnprocessable();
    $this->actingAs($other->user)->get(route('school.attendance.index', $this->school))->assertOk()->assertSee('You are not a form teacher yet');
});

it('shows the office every class and which registers are taken', function () {
    ($this->save)($this->formTeacher->user, [['enrolment' => $this->enrolments[0], 'status' => 'present']]);

    $this->actingAs(memberOf($this->school, SchoolRole::Principal))->get(route('school.attendance.index', $this->school))
        ->assertOk()
        ->assertSeeInOrder(['JSS 1A', '1 of 2'])
        ->assertSee('Registers taken');
});

it('uses the register on the report card when the form teacher has not typed times present', function () {
    ($this->save)($this->formTeacher->user, [['enrolment' => $this->enrolments[0], 'status' => 'late']]);

    $this->actingAs(memberOf($this->school))->get(route('school.results.report', [$this->school, $this->aisha]))
        ->assertOk()
        ->assertSeeInOrder(['Times school opened', '1', 'Times present', '1', 'Times absent', '0']);
});

it('labels each mark for the register', function () {
    expect(AttendanceStatus::Late->isPresent())->toBeTrue()
        ->and(AttendanceStatus::Excused->isPresent())->toBeFalse()
        ->and(AttendanceStatus::Absent->short())->toBe('A');
});

it('puts today’s register on the class page: editable for the form teacher, read-only for subject teachers', function () {
    $subjectTeacher = staffOf($this->school);
    inSchool($this->school, fn () => $this->arm->classSubjects()->first()->update(['teacher_id' => $subjectTeacher->id]));
    ($this->save)($this->formTeacher->user, [['enrolment' => $this->enrolments[0], 'status' => 'absent']])->assertOk();

    $this->actingAs($this->formTeacher->user)->get(route('school.classes.show', [$this->school, $this->arm]))
        ->assertOk()->assertSee('Today’s register')->assertSee('Mark the rest present')->assertSee('BELLO Aisha');

    $this->actingAs($subjectTeacher->user)->get(route('school.classes.show', [$this->school, $this->arm]))
        ->assertOk()->assertSee('Today’s register')->assertDontSee('Mark the rest present')->assertSee('takes this class’s register');
    ($this->save)($subjectTeacher->user, [['enrolment' => $this->enrolments[0], 'status' => 'present']])->assertForbidden();
});
