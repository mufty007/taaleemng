<?php

use App\Enums\AttendanceStatus;
use App\Enums\SchoolRole;
use App\Enums\TermStatus;
use App\Models\StaffAttendanceRecord;
use App\Models\Term;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->office = memberOf($this->school, SchoolRole::Principal);
    $this->teacher = staffOf($this->school);
    $this->colleague = staffOf($this->school, SchoolRole::Teacher, ['first_name' => 'Chioma', 'last_name' => 'Eze']);

    inSchool($this->school, function () {
        $this->term = Term::current();
        $this->day = $this->term->starts_on->copy()->next(Carbon::TUESDAY);
    });

    $this->travelTo($this->day->copy()->setTime(9, 0));

    $this->save = fn ($user, array $records, ?Carbon $date = null) => $this->actingAs($user)->putJson(route('school.attendance.staff.save', $this->school), [
        'date' => ($date ?? $this->day)->toDateString(),
        'records' => $records,
    ]);
});

it('lets the office take the staff register', function () {
    $this->actingAs($this->office)->get(route('school.attendance.staff', $this->school))
        ->assertOk()
        ->assertSee('Staff register')
        ->assertSee($this->teacher->fullName())
        ->assertSee($this->colleague->fullName());

    ($this->save)($this->office, [
        ['staff' => $this->teacher->id, 'status' => 'present'],
        ['staff' => $this->colleague->id, 'status' => 'absent', 'note' => 'Sick'],
    ])->assertOk()->assertJson(['saved' => 2]);

    ($this->save)($this->office, [
        ['staff' => $this->colleague->id, 'status' => 'late'],
    ])->assertOk();

    inSchool($this->school, function () {
        expect(StaffAttendanceRecord::query()->count())->toBe(2)
            ->and(StaffAttendanceRecord::query()->where('staff_id', $this->colleague->id)->sole()->status)->toBe(AttendanceStatus::Late);
    });
});

it('refuses weekends, future days and closed terms', function () {
    ($this->save)($this->office, [['staff' => $this->teacher->id, 'status' => 'present']], $this->day->copy()->next(Carbon::SATURDAY))->assertUnprocessable();
    ($this->save)($this->office, [['staff' => $this->teacher->id, 'status' => 'present']], $this->day->copy()->addDay())->assertUnprocessable();

    inSchool($this->school, fn () => $this->term->update(['status' => TermStatus::Closed]));
    ($this->save)($this->office, [['staff' => $this->teacher->id, 'status' => 'present']])->assertUnprocessable()->assertJsonValidationErrors('date');
});

it('keeps teachers without attendance.view out of the staff register', function () {
    $this->actingAs($this->teacher->user)->get(route('school.attendance.staff', $this->school))->assertForbidden();
    ($this->save)($this->teacher->user, [['staff' => $this->teacher->id, 'status' => 'present']])->assertForbidden();
});

it('links staff register from the office attendance board', function () {
    $this->actingAs($this->office)->get(route('school.attendance.index', $this->school))
        ->assertOk()
        ->assertSee('Staff register')
        ->assertSee(route('school.attendance.staff', [$this->school, 'date' => $this->day->toDateString()], false));
});
