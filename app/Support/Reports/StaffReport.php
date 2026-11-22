<?php

namespace App\Support\Reports;

use App\Enums\Gender;
use App\Enums\StaffStatus;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Staff;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Support\Attendance\AttendanceReport;
use App\Support\Money;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The staff room as of today: headcount by department, gender and contract, the monthly
 * wage bill from salaries on file, who teaches what (and how many periods a week), the
 * subjects nobody is assigned to, length of service and qualifications.
 *
 * Pay is only shown to people who run payroll; a principal sees the same report without it.
 */
final class StaffReport implements SchoolReport
{
    /** @var Collection<int, Staff>|null */
    private ?Collection $staff = null;

    /** @var array<int, array{subjects: int, classes: int, assignments: int, periods: int, form_class: ?string}>|null by staff id */
    private ?array $loads = null;

    public function __construct(public readonly bool $showPay = true) {}

    public function periodLabel(): string
    {
        return 'As of '.today()->format('j M Y');
    }

    public function figures(): array
    {
        $all = $this->staff();
        $active = $all->where('status', StaffStatus::Active);
        $teaching = $active->filter(fn (Staff $member) => isset($this->loads()[$member->id]) && $this->loads()[$member->id]['assignments'] > 0);
        $term = Term::current();
        $served = $active->filter(fn (Staff $member) => $member->date_employed !== null);

        return [
            'showPay' => $this->showPay,
            'stats' => [
                'on_file' => $all->count(),
                'active' => $active->count(),
                'on_leave' => $all->where('status', StaffStatus::OnLeave)->count(),
                'exited' => $all->where('status', StaffStatus::Exited)->count(),
                'teaching' => $teaching->count(),
                'non_teaching' => $active->count() - $teaching->count(),
                'men' => $active->where('gender', Gender::Male)->count(),
                'women' => $active->where('gender', Gender::Female)->count(),
                'wage_bill' => (int) $active->sum(fn (Staff $member) => $member->netMonthlyPay() ?? 0),
                'unsalaried' => $active->filter(fn (Staff $member) => $member->netMonthlyPay() === null)->count(),
                'average_service' => $served->isEmpty() ? null : round((float) $served->avg(fn (Staff $member) => $member->date_employed->diffInYears(today())), 1),
                'presence' => $term ? AttendanceReport::staff($term, limit: 0)['summary']['rate'] : null,
            ],
            'term' => $term,
            'departments' => $this->departments($active),
            'contracts' => $active->countBy(fn (Staff $member) => $member->employment_type->label())->sortDesc()->all(),
            'service' => $this->service($active),
            'qualifications' => $this->qualifications($active),
            'load' => $this->teachingLoad($teaching),
            'unassigned' => ClassSubject::query()->whereNull('teacher_id')->with(['subject', 'classArm.level'])->get()
                ->sortBy(fn (ClassSubject $classSubject) => EnrolmentReport::armSortKey($classSubject->classArm).' '.$classSubject->subject->name)
                ->values(),
        ];
    }

    public function csv(): StreamedResponse
    {
        $rows = $this->staff()->map(function (Staff $member): array {
            $load = $this->loads()[$member->id] ?? null;

            return [
                $member->staff_no,
                $member->fullName(),
                $member->gender->label(),
                $member->designation,
                $member->department,
                $member->employment_type->label(),
                $member->status->label(),
                $member->date_employed?->format('Y-m-d'),
                $member->date_employed ? (int) $member->date_employed->diffInYears(today()) : null,
                $member->qualification,
                $member->phone,
                $member->email,
                $load['assignments'] ?? 0,
                $load['periods'] ?? 0,
                $load['form_class'] ?? null,
                ...($this->showPay ? [$member->netMonthlyPay() !== null ? Money::toNaira($member->netMonthlyPay()) : null] : []),
            ];
        });

        return CsvDownload::make(
            'staff-'.today()->format('Y-m-d').'.csv',
            ['Staff no.', 'Name', 'Gender', 'Designation', 'Department', 'Employment', 'Status', 'Date employed', 'Years of service', 'Qualification', 'Phone', 'Email', 'Subject classes', 'Periods a week', 'Form class', ...($this->showPay ? ['Net monthly pay (₦)'] : [])],
            $rows,
        );
    }

    /**
     * @return Collection<int, Staff>
     */
    private function staff(): Collection
    {
        return $this->staff ??= Staff::query()->orderBy('last_name')->orderBy('first_name')->get();
    }

    /**
     * What each teacher is down for: subjects, class arms, periods on the timetable, form class.
     *
     * @return array<int, array{subjects: int, classes: int, assignments: int, periods: int, form_class: ?string}>
     */
    private function loads(): array
    {
        if ($this->loads !== null) {
            return $this->loads;
        }

        $assignments = ClassSubject::query()->whereNotNull('teacher_id')->get(['id', 'class_arm_id', 'subject_id', 'teacher_id']);
        $periods = TimetableEntry::query()
            ->whereIn('class_subject_id', $assignments->modelKeys())
            ->selectRaw('class_subject_id, count(*) as total')
            ->groupBy('class_subject_id')
            ->pluck('total', 'class_subject_id');
        $formClasses = ClassArm::query()->whereNotNull('form_teacher_id')->with('level')->get()
            ->groupBy('form_teacher_id')
            ->map(fn (Collection $arms) => $arms->map(fn (ClassArm $arm) => $arm->displayName())->join(', '));

        $loads = [];

        foreach ($assignments->groupBy('teacher_id') as $teacherId => $group) {
            $loads[$teacherId] = [
                'subjects' => $group->pluck('subject_id')->unique()->count(),
                'classes' => $group->pluck('class_arm_id')->unique()->count(),
                'assignments' => $group->count(),
                'periods' => (int) $group->sum(fn (ClassSubject $classSubject) => (int) ($periods[$classSubject->id] ?? 0)),
                'form_class' => $formClasses->get($teacherId),
            ];
        }

        foreach ($formClasses as $teacherId => $names) {
            $loads[$teacherId] ??= ['subjects' => 0, 'classes' => 0, 'assignments' => 0, 'periods' => 0, 'form_class' => $names];
        }

        return $this->loads = $loads;
    }

    /**
     * @param  Collection<int, Staff>  $active
     * @return list<array{department: string, staff: int, men: int, women: int, wage_bill: int}>
     */
    private function departments(Collection $active): array
    {
        return $active->groupBy(fn (Staff $member) => $member->department ?: 'Unassigned')
            ->map(fn (Collection $group, string $department) => [
                'department' => $department,
                'staff' => $group->count(),
                'men' => $group->where('gender', Gender::Male)->count(),
                'women' => $group->where('gender', Gender::Female)->count(),
                'wage_bill' => (int) $group->sum(fn (Staff $member) => $member->netMonthlyPay() ?? 0),
            ])
            ->sortByDesc('staff')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Staff>  $active
     * @return array<string, int>
     */
    private function service(Collection $active): array
    {
        $bands = ['Under 1 year' => 0, '1–2 years' => 0, '3–5 years' => 0, '6–10 years' => 0, 'Over 10 years' => 0, 'Not recorded' => 0];

        foreach ($active as $member) {
            $years = $member->date_employed?->diffInYears(today());
            $band = match (true) {
                $years === null => 'Not recorded',
                $years < 1 => 'Under 1 year',
                $years < 3 => '1–2 years',
                $years < 6 => '3–5 years',
                $years <= 10 => '6–10 years',
                default => 'Over 10 years',
            };
            $bands[$band]++;
        }

        return array_filter($bands, fn (int $count, string $band) => $band !== 'Not recorded' || $count > 0, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Highest qualification as typed on each staff record, grouped by its first word (NCE, B.Ed, M.Ed…).
     *
     * @param  Collection<int, Staff>  $active
     * @return array<string, int>
     */
    private function qualifications(Collection $active): array
    {
        $counts = $active
            ->countBy(fn (Staff $member) => filled($member->qualification) ? (string) preg_split('/[\s,(]+/', trim((string) $member->qualification))[0] : 'Not recorded')
            ->sortDesc();
        $unknown = $counts->pull('Not recorded', 0);
        $other = $counts->skip(8)->sum();

        return $counts->take(8)
            ->when($other > 0, fn (Collection $rows) => $rows->put('Other', $other))
            ->when($unknown > 0, fn (Collection $rows) => $rows->put('Not recorded', $unknown))
            ->all();
    }

    /**
     * @param  Collection<int, Staff>  $teaching
     * @return Collection<int, array{staff: Staff, subjects: int, classes: int, assignments: int, periods: int, form_class: ?string}>
     */
    private function teachingLoad(Collection $teaching): Collection
    {
        return $teaching
            ->map(fn (Staff $member) => ['staff' => $member, ...$this->loads()[$member->id]])
            ->sortBy([['periods', 'desc'], ['assignments', 'desc']])
            ->values();
    }
}
