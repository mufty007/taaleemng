<?php

use App\Enums\AttendanceStatus;
use App\Enums\SchoolRole;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\StaffAttendanceRecord;
use App\Models\Term;
use App\Support\Attendance\AttendanceReport;
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

it('shows the office term rates, chronic absentees and staff presence', function () {
    inSchool($this->school, function () {
        foreach (range(0, 4) as $offset) {
            $date = $this->day->copy()->addDays($offset);
            if ($date->isWeekend()) {
                continue;
            }

            AttendanceRecord::query()->create([
                'term_id' => $this->term->id,
                'class_arm_id' => $this->arm->id,
                'enrolment_id' => $this->aishaEnrolment,
                'date' => $date,
                'status' => AttendanceStatus::Present,
                'recorded_by' => $this->office->id,
            ]);
            AttendanceRecord::query()->create([
                'term_id' => $this->term->id,
                'class_arm_id' => $this->arm->id,
                'enrolment_id' => $this->chidiEnrolment,
                'date' => $date,
                'status' => AttendanceStatus::Absent,
                'recorded_by' => $this->office->id,
            ]);
            StaffAttendanceRecord::query()->create([
                'term_id' => $this->term->id,
                'staff_id' => $this->teacher->id,
                'date' => $date,
                'status' => AttendanceStatus::Late,
                'recorded_by' => $this->office->id,
            ]);
        }
    });

    $this->actingAs($this->office)->get(route('school.attendance.reports', $this->school))
        ->assertOk()
        ->assertSee('Term report')
        ->assertSee('Chronic absentees')
        ->assertSee('OKAFOR Chidi')
        ->assertSee($this->teacher->fullName())
        ->assertSee('JSS 1A');

    inSchool($this->school, function () {
        $students = AttendanceReport::schoolStudents($this->term);
        $absentees = AttendanceReport::chronicAbsentees($this->term);
        $staff = AttendanceReport::staff($this->term);

        expect($students['rate'])->toBe(50)
            ->and($absentees->first()['admission_no'])->toBe($this->chidi->admission_no)
            ->and($staff['summary']['rate'])->toBe(100)
            ->and($staff['rows']->first()['staff'])->toBe($this->teacher->fullName());
    });
});

it('keeps teachers without attendance.view out of reports', function () {
    $this->actingAs($this->teacher->user)->get(route('school.attendance.reports', $this->school))->assertForbidden();
});

it('links reports from the attendance board', function () {
    $this->actingAs($this->office)->get(route('school.attendance.index', $this->school))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee(route('school.attendance.reports', $this->school, false));
});
