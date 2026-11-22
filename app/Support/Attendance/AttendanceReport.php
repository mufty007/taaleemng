<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Term-wide attendance picture for the office: class rates, chronic absentees,
 * and staff presence. Built from the daily registers.
 */
final class AttendanceReport
{
    /**
     * Students whose presence rate is below the threshold (default 80%).
     * Only includes students with at least one register mark this term.
     *
     * @return Collection<int, array{enrolment_id: int, student: string, admission_no: string, class: string, days: int, absent: int, late: int, rate: int}>
     */
    public static function chronicAbsentees(Term $term, int $threshold = 80, int $limit = 25): Collection
    {
        $enrolments = Enrolment::query()
            ->with(['student', 'classArm.level'])
            ->where('academic_session_id', $term->academic_session_id)
            ->get()
            ->keyBy('id');

        if ($enrolments->isEmpty()) {
            return collect();
        }

        $summary = AttendanceSummary::forTerm($term, $enrolments->keys()->all());

        return collect($summary)
            ->filter(fn (array $row): bool => $row['days'] > 0 && $row['rate'] !== null && $row['rate'] < $threshold)
            ->map(function (array $row, int $enrolmentId) use ($enrolments): ?array {
                $enrolment = $enrolments->get($enrolmentId);

                if ($enrolment === null || $enrolment->student === null) {
                    return null;
                }

                return [
                    'enrolment_id' => $enrolmentId,
                    'student' => $enrolment->student->registerName(),
                    'admission_no' => $enrolment->student->admission_no,
                    'class' => $enrolment->classArm?->displayName() ?? '—',
                    'days' => $row['days'],
                    'absent' => $row['absent'],
                    'late' => $row['late'],
                    'rate' => $row['rate'],
                ];
            })
            ->filter()
            ->sortBy([
                ['rate', 'asc'],
                ['absent', 'desc'],
                ['student', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    /**
     * Per-class presence rate for the term.
     *
     * @return Collection<int, array{arm: ClassArm, students: int, days_taken: int, marked: int, present: int, absent: int, late: int, rate: int|null}>
     */
    public static function byClass(Term $term): Collection
    {
        $arms = ClassArm::query()
            ->with('level')
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $term->academic_session_id)])
            ->get()
            ->filter(fn (ClassArm $arm) => $arm->enrolments_count > 0)
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();

        $totals = AttendanceRecord::query()
            ->where('term_id', $term->id)
            ->selectRaw('class_arm_id, status, count(*) as total')
            ->groupBy('class_arm_id', 'status')
            ->get()
            ->groupBy('class_arm_id');

        $daysTaken = AttendanceRecord::query()
            ->where('term_id', $term->id)
            ->selectRaw('class_arm_id, count(distinct date) as days')
            ->groupBy('class_arm_id')
            ->pluck('days', 'class_arm_id');

        return $arms->map(function (ClassArm $arm) use ($totals, $daysTaken): array {
            $rows = $totals->get($arm->id, collect());
            $marked = (int) $rows->sum(fn ($row) => (int) $row->getAttribute('total'));
            $present = (int) $rows
                ->filter(fn ($row) => $row->status === AttendanceStatus::Present || $row->status === AttendanceStatus::Late)
                ->sum(fn ($row) => (int) $row->getAttribute('total'));

            $count = fn (AttendanceStatus $status): int => (int) $rows->filter(fn ($row) => $row->status === $status)->sum(fn ($row) => (int) $row->getAttribute('total'));

            return [
                'arm' => $arm,
                'students' => $arm->enrolments_count,
                'days_taken' => (int) ($daysTaken[$arm->id] ?? 0),
                'marked' => $marked,
                'present' => $present,
                'absent' => $count(AttendanceStatus::Absent),
                'late' => $count(AttendanceStatus::Late),
                'rate' => $marked === 0 ? null : (int) round($present / $marked * 100),
            ];
        });
    }

    /**
     * Whole-school student presence for each school day the register was taken.
     *
     * @return Collection<string, array{date: Carbon, marked: int, present: int, absent: int, late: int, rate: int}> by Y-m-d
     */
    public static function daily(Term $term): Collection
    {
        return AttendanceRecord::query()
            ->where('term_id', $term->id)
            ->selectRaw('date, status, count(*) as total')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy(fn (AttendanceRecord $row) => $row->date->toDateString())
            ->map(function (Collection $rows): array {
                $count = fn (AttendanceStatus $status): int => (int) $rows->filter(fn ($row) => $row->status === $status)->sum(fn ($row) => (int) $row->getAttribute('total'));
                $marked = (int) $rows->sum(fn ($row) => (int) $row->getAttribute('total'));
                $present = $count(AttendanceStatus::Present) + $count(AttendanceStatus::Late);

                return [
                    'date' => $rows->first()->date,
                    'marked' => $marked,
                    'present' => $present,
                    'absent' => $count(AttendanceStatus::Absent),
                    'late' => $count(AttendanceStatus::Late),
                    'rate' => (int) round($present / $marked * 100),
                ];
            });
    }

    /**
     * Whole-school student presence rate this term.
     *
     * @return array{marked: int, present: int, absent: int, late: int, rate: int|null}
     */
    public static function schoolStudents(Term $term): array
    {
        $rows = AttendanceRecord::query()
            ->where('term_id', $term->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->getAttribute('total')]);

        $marked = (int) $rows->sum();
        $present = (int) (($rows['present'] ?? 0) + ($rows['late'] ?? 0));

        return [
            'marked' => $marked,
            'present' => $present,
            'absent' => (int) ($rows['absent'] ?? 0),
            'late' => (int) ($rows['late'] ?? 0),
            'rate' => $marked === 0 ? null : (int) round($present / $marked * 100),
        ];
    }

    /**
     * Staff presence this term, lowest rates first.
     *
     * @return array{summary: array{marked: int, present: int, absent: int, late: int, rate: int|null}, rows: Collection<int, array{staff: string, staff_no: string, designation: ?string, days: int, absent: int, late: int, rate: int}>}
     */
    public static function staff(Term $term, int $limit = 20): array
    {
        $members = Staff::query()->active()->orderBy('last_name')->orderBy('first_name')->get()->keyBy('id');

        $counts = StaffAttendanceRecord::query()
            ->where('term_id', $term->id)
            ->whereIn('staff_id', $members->keys())
            ->selectRaw('staff_id, status, count(*) as total')
            ->groupBy('staff_id', 'status')
            ->get();

        $byStaff = [];

        foreach ($members as $id => $member) {
            $byStaff[$id] = ['days' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'rate' => null];
        }

        foreach ($counts as $row) {
            $byStaff[$row->staff_id][$row->status->value] = (int) $row->getAttribute('total');
            $byStaff[$row->staff_id]['days'] += (int) $row->getAttribute('total');
        }

        $marked = 0;
        $presentMarks = 0;
        $absent = 0;
        $late = 0;

        $rows = collect($byStaff)
            ->map(function (array $row, int $staffId) use ($members, &$marked, &$presentMarks, &$absent, &$late): ?array {
                if ($row['days'] === 0) {
                    return null;
                }

                $rate = (int) round(($row['present'] + $row['late']) / $row['days'] * 100);
                $marked += $row['days'];
                $presentMarks += $row['present'] + $row['late'];
                $absent += $row['absent'];
                $late += $row['late'];
                $member = $members->get($staffId);

                return [
                    'staff' => $member->fullName(),
                    'staff_no' => $member->staff_no,
                    'designation' => $member->designation,
                    'days' => $row['days'],
                    'absent' => $row['absent'],
                    'late' => $row['late'],
                    'rate' => $rate,
                ];
            })
            ->filter()
            ->sortBy([
                ['rate', 'asc'],
                ['absent', 'desc'],
                ['staff', 'asc'],
            ])
            ->take($limit)
            ->values();

        return [
            'summary' => [
                'marked' => $marked,
                'present' => $presentMarks,
                'absent' => $absent,
                'late' => $late,
                'rate' => $marked === 0 ? null : (int) round($presentMarks / $marked * 100),
            ],
            'rows' => $rows,
        ];
    }
}
