<?php

namespace App\Support\Reports;

use App\Models\Enrolment;
use App\Models\Term;
use App\Support\Attendance\AttendanceReport;
use App\Support\Attendance\AttendanceSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A term's registers for the office: presence, absence and lateness by class, how presence
 * moved week by week and across the school week, chronic absentees, and staff presence.
 */
final class TermAttendanceReport implements SchoolReport
{
    /**
     * Students below this presence rate are chronic absentees.
     */
    public const THRESHOLD = 80;

    public function __construct(public readonly Term $term) {}

    public function periodLabel(): string
    {
        return $this->term->name.' · '.$this->term->academicSession->name;
    }

    public function figures(): array
    {
        $daily = AttendanceReport::daily($this->term);
        $staff = AttendanceReport::staff($this->term, limit: 15);
        $absentees = AttendanceReport::chronicAbsentees($this->term, self::THRESHOLD, limit: PHP_INT_MAX);

        return [
            'term' => $this->term,
            'threshold' => self::THRESHOLD,
            'students' => AttendanceReport::schoolStudents($this->term),
            'classes' => AttendanceReport::byClass($this->term),
            'days' => $daily->count(),
            'weeks' => $this->weeks($daily),
            'weekdays' => $this->weekdays($daily),
            'lowestDays' => $daily->filter(fn (array $day) => $day['marked'] > 0)->sortBy('rate')->take(5)->values(),
            'absentees' => $absentees->take(30),
            'absenteeCount' => $absentees->count(),
            'staffSummary' => $staff['summary'],
            'staffRows' => $staff['rows'],
        ];
    }

    public function csv(): StreamedResponse
    {
        $enrolments = Enrolment::query()
            ->where('academic_session_id', $this->term->academic_session_id)
            ->whereHas('student')
            ->with(['student', 'classArm.level'])
            ->get();

        $summary = AttendanceSummary::forTerm($this->term, $enrolments->modelKeys());

        $rows = $enrolments
            ->sortBy(fn (Enrolment $enrolment) => EnrolmentReport::armSortKey($enrolment->classArm).' '.$enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->map(fn (Enrolment $enrolment) => [
                $enrolment->classArm->displayName(),
                $enrolment->student->admission_no,
                $enrolment->student->registerName(),
                $summary[$enrolment->id]['days'],
                $summary[$enrolment->id]['present'],
                $summary[$enrolment->id]['late'],
                $summary[$enrolment->id]['absent'],
                $summary[$enrolment->id]['excused'],
                $summary[$enrolment->id]['rate'],
            ]);

        return CsvDownload::make(
            'attendance-'.str($this->term->name.' '.$this->term->academicSession->name)->slug().'.csv',
            ['Class', 'Admission no.', 'Student', 'Days marked', 'Present', 'Late', 'Absent', 'Excused', 'Presence %'],
            $rows,
        );
    }

    /**
     * @param  Collection<string, array{date: Carbon, marked: int, present: int, absent: int, late: int, rate: int}>  $daily
     * @return list<array{label: string, days: int, rate: int, absent: int}>
     */
    private function weeks(Collection $daily): array
    {
        return $daily
            ->groupBy(fn (array $day) => $this->term->weekOf($day['date']) ?? 0)
            ->sortKeys()
            ->map(fn (Collection $days, int $week) => [
                'label' => $week === 0 ? 'Outside term' : 'Week '.$week,
                'days' => $days->count(),
                'rate' => (int) round($days->sum('present') / max(1, $days->sum('marked')) * 100),
                'absent' => (int) $days->sum('absent'),
            ])
            ->values()
            ->all();
    }

    /**
     * Presence by day of the week, Monday to Friday (and any weekend days the register was taken).
     *
     * @param  Collection<string, array{date: Carbon, marked: int, present: int, absent: int, late: int, rate: int}>  $daily
     * @return list<array{label: string, days: int, rate: ?int, late: int}>
     */
    private function weekdays(Collection $daily): array
    {
        $byDay = $daily->groupBy(fn (array $day) => $day['date']->dayOfWeekIso);

        return collect(range(1, 7))
            ->filter(fn (int $day) => $day <= 5 || $byDay->has($day))
            ->map(function (int $day) use ($byDay): array {
                $days = $byDay->get($day, collect());

                return [
                    'label' => now()->startOfWeek()->addDays($day - 1)->format('l'),
                    'days' => $days->count(),
                    'rate' => $days->isEmpty() ? null : (int) round($days->sum('present') / max(1, $days->sum('marked')) * 100),
                    'late' => (int) $days->sum('late'),
                ];
            })
            ->values()
            ->all();
    }
}
