<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\StaffAttendanceRecord;
use App\Models\User;
use App\Support\Attendance\ClassRegister;
use App\Support\Attendance\StaffRegister;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mark every unmarked student (and optionally staff) present for one school day.
 */
final class MarkRemainingPresent
{
    /**
     * @return array{classes: int, students: int, staff: int}
     */
    public function handle(Carbon $date, User $recorder, bool $includeStaff = true): array
    {
        $date = $date->copy()->startOfDay();
        $term = ClassRegister::termFor($date);

        if ($term === null || $date->isWeekend() || $date->isFuture()) {
            throw ValidationException::withMessages(['date' => 'The register can only be taken on a school day within a term, up to today.']);
        }

        if ($term->isClosed()) {
            throw ValidationException::withMessages(['date' => "{$term->name} is closed. Ask an admin to reopen it to change the register."]);
        }

        $students = 0;
        $classes = 0;
        $staff = 0;

        DB::transaction(function () use ($date, $term, $recorder, $includeStaff, &$students, &$classes, &$staff): void {
            foreach (ClassArm::query()->get() as $arm) {
                $enrolments = ClassRegister::enrolments($arm, $term);

                if ($enrolments->isEmpty()) {
                    continue;
                }

                $marked = AttendanceRecord::query()
                    ->where('class_arm_id', $arm->id)
                    ->whereDate('date', $date)
                    ->pluck('enrolment_id')
                    ->all();

                $unmarked = $enrolments->whereNotIn('id', $marked);

                if ($unmarked->isEmpty()) {
                    continue;
                }

                $classes++;

                foreach ($unmarked as $enrolment) {
                    AttendanceRecord::query()->create([
                        'term_id' => $term->id,
                        'class_arm_id' => $arm->id,
                        'enrolment_id' => $enrolment->id,
                        'date' => $date->toDateString(),
                        'status' => AttendanceStatus::Present,
                        'recorded_by' => $recorder->id,
                    ]);
                    $students++;
                }
            }

            if (! $includeStaff) {
                return;
            }

            $staffIds = StaffRegister::staffIds();
            $markedStaff = StaffAttendanceRecord::query()
                ->whereDate('date', $date)
                ->whereIn('staff_id', $staffIds)
                ->pluck('staff_id')
                ->all();

            foreach (array_diff($staffIds, $markedStaff) as $staffId) {
                StaffAttendanceRecord::query()->create([
                    'term_id' => $term->id,
                    'staff_id' => $staffId,
                    'date' => $date->toDateString(),
                    'status' => AttendanceStatus::Present,
                    'recorded_by' => $recorder->id,
                ]);
                $staff++;
            }
        });

        return compact('classes', 'students', 'staff');
    }
}
