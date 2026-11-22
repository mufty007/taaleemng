<?php

use App\Enums\AttendanceStatus;
use App\Enums\SchoolRole;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\StaffAttendanceRecord;
use App\Models\Term;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->office = memberOf($this->school, SchoolRole::Principal);
    $this->teacher = staffOf($this->school);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'])->student;
    $this->chidi = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Chidi', 'last_name' => 'Okafor'])->student;

    inSchool($this->school, function () {
        $this->arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->term = Term::current();
        $this->day = $this->term->starts_on->copy()->next(Carbon::TUESDAY);
        $this->aishaEnrolment = $this->aisha->currentEnrolment->id;
        $this->chidiEnrolment = $this->chidi->currentEnrolment->id;
    });

    $this->travelTo($this->day->copy()->setTime(9, 0));
});

it('marks unmarked students and staff present for the day', function () {
    inSchool($this->school, function () {
        AttendanceRecord::query()->create([
            'term_id' => $this->term->id,
            'class_arm_id' => $this->arm->id,
            'enrolment_id' => $this->aishaEnrolment,
            'date' => $this->day,
            'status' => AttendanceStatus::Absent,
            'recorded_by' => $this->office->id,
        ]);
    });

    $this->actingAs($this->office)->post(route('school.attendance.mark-remaining', $this->school), [
        'date' => $this->day->toDateString(),
    ])->assertRedirect(route('school.attendance.index', [$this->school, 'date' => $this->day->toDateString()]));

    inSchool($this->school, function () {
        expect(AttendanceRecord::query()->where('enrolment_id', $this->aishaEnrolment)->sole()->status)->toBe(AttendanceStatus::Absent)
            ->and(AttendanceRecord::query()->where('enrolment_id', $this->chidiEnrolment)->sole()->status)->toBe(AttendanceStatus::Present)
            ->and(StaffAttendanceRecord::query()->where('staff_id', $this->teacher->id)->sole()->status)->toBe(AttendanceStatus::Present);
    });
});

it('refuses teachers without attendance.view', function () {
    $this->actingAs($this->teacher->user)->post(route('school.attendance.mark-remaining', $this->school), [
        'date' => $this->day->toDateString(),
    ])->assertForbidden();
});
