<?php

use App\Actions\Timetable\AutofillTimetable;
use App\Enums\SchoolRole;
use App\Models\ClassArm;
use App\Models\Period;
use App\Models\Subject;
use App\Models\TimetableEntry;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->principal = memberOf($this->school, SchoolRole::Principal);
    $this->teacher = staffOf($this->school, SchoolRole::Teacher, ['first_name' => 'Emeka', 'last_name' => 'Okonkwo']);
    enrol($this->school, 'JSS 1', 'A');
    enrol($this->school, 'JSS 2', 'A');

    $this->actingAs($this->principal)->post(route('school.timetable.periods.defaults', $this->school))->assertSessionHas('status');

    inSchool($this->school, function () {
        $this->jss1a = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->jss1b = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 2'))->where('name', 'A')->sole();
        $maths = Subject::query()->where('code', 'MTH')->value('id');
        $this->mathsA = $this->jss1a->classSubjects()->where('subject_id', $maths)->sole();
        $this->mathsB = $this->jss1b->classSubjects()->where('subject_id', $maths)->sole();
        $this->mathsA->update(['teacher_id' => $this->teacher->id]);
        $this->mathsB->update(['teacher_id' => $this->teacher->id]);
        $this->period1 = Period::query()->where('name', 'Period 1')->sole();
        $this->break = Period::query()->where('name', 'Short break')->sole();
    });

    $this->slot = fn (ClassArm $arm, array $data) => $this->actingAs($this->principal)->putJson(route('school.timetable.slots', [$this->school, $arm]), $data);
});

it('sets up a standard school day', function () {
    expect(inSchool($this->school, fn () => Period::query()->count()))->toBe(count(Period::DEFAULTS));

    $this->actingAs($this->principal)->get(route('school.timetable.periods', $this->school))->assertOk()->assertSee('Long break');
    $this->actingAs($this->principal)->post(route('school.timetable.periods.defaults', $this->school))->assertStatus(409);
});

it('places lessons by hand and refuses to put a teacher in two classes at once', function () {
    ($this->slot)($this->jss1a, ['day' => 1, 'period_id' => $this->period1->id, 'class_subject_id' => $this->mathsA->id])
        ->assertOk()->assertJson(['subject' => 'Mathematics']);

    ($this->slot)($this->jss1b, ['day' => 1, 'period_id' => $this->period1->id, 'class_subject_id' => $this->mathsB->id])
        ->assertUnprocessable()->assertJsonValidationErrors('class_subject_id');
    ($this->slot)($this->jss1b, ['day' => 2, 'period_id' => $this->period1->id, 'class_subject_id' => $this->mathsB->id])->assertOk();

    // A subject from another class, and a lesson in a break, are refused.
    ($this->slot)($this->jss1a, ['day' => 3, 'period_id' => $this->period1->id, 'class_subject_id' => $this->mathsB->id])->assertUnprocessable();
    ($this->slot)($this->jss1a, ['day' => 3, 'period_id' => $this->break->id, 'class_subject_id' => $this->mathsA->id])->assertUnprocessable();

    ($this->slot)($this->jss1a, ['day' => 1, 'period_id' => $this->period1->id, 'class_subject_id' => null])->assertOk()->assertJson(['cleared' => true]);
    expect(inSchool($this->school, fn () => TimetableEntry::query()->count()))->toBe(1);
});

it('fills a class’s week without clashing with the teacher’s other classes', function () {
    $resultA = inSchool($this->school, fn () => app(AutofillTimetable::class)->handle($this->jss1a));
    $resultB = inSchool($this->school, fn () => app(AutofillTimetable::class)->handle($this->jss1b));

    expect($resultA['placed'])->toBeGreaterThan(20)->and($resultB['placed'])->toBeGreaterThan(20);

    inSchool($this->school, function () {
        expect(TimetableEntry::query()->where('class_subject_id', $this->mathsA->id)->count())->toBe(5)
            ->and(TimetableEntry::query()->where('class_subject_id', $this->mathsA->id)->distinct()->count('day'))->toBe(5);

        $teacherSlots = TimetableEntry::query()->whereIn('class_subject_id', [$this->mathsA->id, $this->mathsB->id])->get()
            ->map(fn (TimetableEntry $entry) => $entry->day.'-'.$entry->period_id);
        expect($teacherSlots->duplicates())->toBeEmpty();
        expect(TimetableEntry::query()->where('period_id', $this->break->id)->exists())->toBeFalse();
    });
});

it('shows a teacher their own week, and their lessons today on the dashboard', function () {
    $this->travelTo(now()->next('Monday')->setTime(8, 0));
    ($this->slot)($this->jss1a, ['day' => 1, 'period_id' => $this->period1->id, 'class_subject_id' => $this->mathsA->id])->assertOk();

    $this->actingAs($this->teacher->user)->get(route('school.timetable.index', $this->school))
        ->assertRedirect(route('school.timetable.teacher', [$this->school, $this->teacher]));
    $this->actingAs($this->teacher->user)->get(route('school.timetable.teacher', [$this->school, $this->teacher]))
        ->assertOk()->assertSee('JSS 1A')->assertSee('Mathematics');
    $this->actingAs($this->teacher->user)->get(route('school.dashboard', $this->school))
        ->assertSee('Today’s lessons')->assertSee('7:50 – 8:30');

    $this->actingAs(staffOf($this->school)->user)->get(route('school.timetable.teacher', [$this->school, $this->teacher]))->assertForbidden();
    $this->actingAs($this->teacher->user)->putJson(route('school.timetable.slots', [$this->school, $this->jss1a]), ['day' => 2, 'period_id' => $this->period1->id, 'class_subject_id' => $this->mathsA->id])->assertForbidden();
});

it('shows the class timetable to its students and parents, and prints it with the school’s letterhead', function () {
    $student = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha']);
    ($this->slot)($this->jss1a, ['day' => 1, 'period_id' => $this->period1->id, 'class_subject_id' => $this->mathsA->id])->assertOk();

    $this->actingAs($student->student->user)->get(route('school.portal', $this->school))->assertOk()->assertSee('Timetable')->assertSee('Okonkwo');
    $this->actingAs($student->guardian->user)->get(route('school.portal.child', [$this->school, $student->student]))->assertOk()->assertSee('Okonkwo');
    $this->actingAs($student->student->user)->get(route('school.timetable.index', $this->school))->assertForbidden();

    $this->actingAs($this->principal)->get(route('school.timetable.print', [$this->school, 'kind' => 'class', 'ulid' => $this->jss1a->ulid]))
        ->assertOk()->assertSee('Timetable')->assertSee('JSS 1A')->assertSee('Powered by');
});
