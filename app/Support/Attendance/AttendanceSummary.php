<?php

namespace App\Support\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Term;

/**
 * Term totals from the register: how many days each student was marked, and how.
 */
final class AttendanceSummary
{
    /**
     * @param  list<int>  $enrolmentIds
     * @return array<int, array{days: int, present: int, late: int, absent: int, excused: int, rate: int|null}> by enrolment id
     */
    public static function forTerm(Term $term, array $enrolmentIds): array
    {
        $counts = AttendanceRecord::query()
            ->where('term_id', $term->id)
            ->whereIn('enrolment_id', $enrolmentIds)
            ->selectRaw('enrolment_id, status, count(*) as total')
            ->groupBy('enrolment_id', 'status')
            ->get();

        $summary = [];

        foreach ($enrolmentIds as $enrolmentId) {
            $summary[$enrolmentId] = ['days' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'rate' => null];
        }

        foreach ($counts as $row) {
            $status = $row->status;
            $summary[$row->enrolment_id][$status->value] = (int) $row->getAttribute('total');
            $summary[$row->enrolment_id]['days'] += (int) $row->getAttribute('total');
        }

        foreach ($summary as $enrolmentId => $row) {
            $summary[$enrolmentId]['rate'] = $row['days'] === 0 ? null : (int) round(($row['present'] + $row['late']) / $row['days'] * 100);
        }

        return $summary;
    }

    /**
     * Distinct days the class register was taken this term.
     */
    public static function daysTaken(Term $term, int $classArmId): int
    {
        return AttendanceRecord::query()->where('term_id', $term->id)->where('class_arm_id', $classArmId)->distinct()->count('date');
    }
}
