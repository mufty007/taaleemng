<?php

namespace App\Http\Controllers\School;

use App\Enums\MembershipStatus;
use App\Enums\SchoolRole;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Dashboard\SchoolPulse;
use App\Support\Dashboard\TeacherDay;
use App\Support\Finance\CashflowPeriod;
use App\Support\Money;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Timetable\Timetable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * The first page after signing in. Each role sees what needs doing first,
 * then its numbers: the office sees the whole school, the bursar the money,
 * a teacher their own classes and marking.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentSchool $currentSchool): View|RedirectResponse
    {
        $school = $currentSchool->getOrFail();
        $user = $request->user();

        $request->session()->put('last_school', $school->slug);

        // Parents and students have their own, simpler portal.
        if (! $user->isStaffInCurrentSchool() && ! $user->is_platform_admin) {
            return redirect()->route('school.portal');
        }

        $session = AcademicSession::current();
        $term = Term::current();
        $staff = $user->staffRecord();
        $mode = match (true) {
            ! $user->can('students.view-all') && $staff !== null => 'teacher',
            $user->can('finance.view') && ! $user->can('academics.manage') => 'bursar',
            default => 'office',
        };

        $pulse = new SchoolPulse($session, $term);
        $teacherDay = $staff ? new TeacherDay($staff, $session, $term) : null;
        $classCount = ClassArm::query()->count();
        $studentCount = Enrolment::query()->where('academic_session_id', $session?->id)->count();
        $staffCount = Staff::query()->where('status', StaffStatus::Active)->count();

        $data = [
            'school' => $school,
            'mode' => $mode,
            'roles' => $user->getRoleNames()
                ->map(fn (string $role): string => SchoolRole::tryFrom($role)?->label() ?? $role)
                ->whenEmpty(fn ($roles) => $roles->push($user->is_platform_admin ? 'Platform admin' : 'Member')),
            'term' => $term,
            'myClasses' => $staff ? $this->myClasses($staff) : collect(),
            'formClasses' => $staff ? $staff->formClasses()->with('level')->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session?->id)])->get() : collect(),
            'recentActivity' => $user->can('activity.view')
                ? Activity::query()->where('school_id', $school->id)->with('causer')->latest()->limit(6)->get()
                : collect(),
            'latestNotices' => Announcement::query()->visibleTo($user)->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])->noticeBoardOrder()->limit(3)->get(),
            'setupSteps' => $this->setupSteps($school->address, $school->phone, $session, $classCount, $studentCount, $staffCount),
        ];

        if ($mode === 'teacher' && $teacherDay) {
            return view('school.dashboard', [...$data, ...$this->teacher($teacherDay, $session)]);
        }

        $today = $user->can('attendance.view') ? $pulse->attendanceToday() : null;
        $fees = $user->can('finance.view') ? $pulse->fees() : null;
        $results = $user->can('results.view') && $term ? $pulse->results() : null;
        $admissions = $user->can('admissions.manage') ? $pulse->admissions() : null;

        return view('school.dashboard', [
            ...$data,
            'stats' => $mode === 'bursar' ? $this->bursarStats($fees) : $this->officeStats($studentCount, $staffCount, $session, $today, $fees, $results),
            'attention' => $this->officeAttention($user, $today, $fees, $results, $admissions, $term),
            'today' => $today,
            'trend' => $today ? $pulse->attendanceTrend() : collect(),
            'fees' => $fees,
            'recentPayments' => $fees ? $pulse->recentPayments() : collect(),
            'cashflow' => $fees ? $this->cashflow($term) : null,
            'results' => $results,
            'admissions' => $admissions,
            'arms' => $pulse->arms(),
            'genderSplit' => Student::query()->where('status', StudentStatus::Active)->selectRaw('gender, count(*) as total')->groupBy('gender')->pluck('total', 'gender'),
            'guardianCount' => Guardian::query()->count(),
            'memberCount' => $school->users()->wherePivot('status', MembershipStatus::Active->value)->count(),
            'subjectCount' => Subject::query()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function teacher(TeacherDay $day, ?AcademicSession $session): array
    {
        $registers = $day->registers();
        $toMark = $day->toMark();
        $progress = $day->scoreProgress();
        $comingUp = $day->comingUp();
        $attention = [];

        foreach ($registers as $register) {
            if (! $register->locked && ! $register->isTaken()) {
                $attention[] = [
                    'tone' => 'gold', 'icon' => 'clipboard-check',
                    'title' => 'Take today’s register for '.$register->arm->displayName(),
                    'body' => $register->rows->whereNotNull('status')->count().' of '.$register->rows->count().' marked so far.',
                    'url' => route('school.classes.show', $register->arm).'#register',
                ];
            }
        }

        if ($toMark->isNotEmpty()) {
            $first = $day->firstUnmarked($toMark->first());
            $attention[] = [
                'tone' => 'brand', 'icon' => 'pen-line',
                'title' => $toMark->sum('unmarked_count').' '.Str::plural('hand-in', (int) $toMark->sum('unmarked_count')).' to mark',
                'body' => $toMark->take(2)->map(fn ($assignment) => $assignment->title.' ('.$assignment->classSubject->classArm->displayName().', '.$assignment->unmarked_count.')')->join(' · '),
                'url' => $first ? route('school.classwork.submissions.show', $first) : route('school.classwork.index'),
            ];
        }

        $behind = $progress->filter(fn (array $row) => $row['rate'] < 100);

        if ($behind->isNotEmpty() && ($day->term?->weekOf(now()) ?? 0) >= 4) {
            $attention[] = [
                'tone' => 'sky', 'icon' => 'file-pen-line',
                'title' => 'Scores still to enter for '.$behind->count().' '.Str::plural('class', $behind->count()),
                'body' => $behind->take(3)->map(fn (array $row) => $row['classSubject']->subject->name.' '.$row['classSubject']->classArm->displayName())->join(' · '),
                'url' => route('school.scores.edit', $behind->first()['classSubject']),
            ];
        }

        return [
            'stats' => $this->teacherStats($day->staff, $session),
            'attention' => $attention,
            'registers' => $registers,
            'toMark' => $toMark,
            'progress' => $progress,
            'dueSoon' => $comingUp['assignments'],
            'todayLessons' => Timetable::today(Timetable::forTeacher($day->staff)),
            'openQuizzes' => $comingUp['quizzes'],
        ];
    }

    /**
     * What the office should look at first, most urgent at the top.
     *
     * @param  array<string, mixed>|null  $today
     * @param  array<string, mixed>|null  $fees
     * @param  array<string, mixed>|null  $results
     * @param  array<string, int>|null  $admissions
     * @return list<array{tone: string, icon: string, title: string, body: string, url: string}>
     */
    private function officeAttention(User $user, ?array $today, ?array $fees, ?array $results, ?array $admissions, ?Term $term): array
    {
        $attention = [];

        if ($today && $today['open'] && $today['waiting']->isNotEmpty() && now()->hour >= 9) {
            $attention[] = [
                'tone' => 'gold', 'icon' => 'clipboard-x',
                'title' => $today['waiting']->count().' '.Str::plural('class', $today['waiting']->count()).' haven’t taken today’s register',
                'body' => $today['waiting']->take(6)->map(fn (ClassArm $arm) => $arm->displayName())->join(', ').($today['waiting']->count() > 6 ? '…' : ''),
                'url' => route('school.attendance.index'),
            ];
        }

        if ($admissions && $admissions['new'] > 0) {
            $attention[] = [
                'tone' => 'brand', 'icon' => 'user-plus',
                'title' => $admissions['new'].' new '.Str::plural('application', $admissions['new']).' to review',
                'body' => 'Screen them, invite them for the entrance exam, or make an offer.',
                'url' => route('school.admissions.index'),
            ];
        }

        if ($results && $results['unscored']->isNotEmpty() && ($term?->weekOf(now()) ?? 0) >= 4) {
            $attention[] = [
                'tone' => 'sky', 'icon' => 'file-pen-line',
                'title' => $results['unscored']->count().' class '.Str::plural('subject', $results['unscored']->count()).' have no scores yet this term',
                'body' => $results['unscored']->take(3)->map(fn (ClassSubject $classSubject) => $classSubject->subject->name.' '.$classSubject->classArm->displayName().($classSubject->teacher ? ' ('.$classSubject->teacher->fullName().')' : ''))->join(' · '),
                'url' => route('school.results.index'),
            ];
        }

        if ($fees && $fees['debtors'] > 0) {
            $attention[] = [
                'tone' => 'danger', 'icon' => 'hand-coins',
                'title' => Money::format($fees['outstanding']).' owed by '.$fees['debtors'].' '.Str::plural('student', $fees['debtors']),
                'body' => 'See who owes, call or text parents, and record payments as they come in.',
                'url' => route('school.debtors.index'),
            ];
        }

        if ($user->can('students.manage')) {
            $withoutPhotos = Student::query()->where('status', StudentStatus::Active)->whereNull('photo_path')->count();

            if ($withoutPhotos > 0) {
                $attention[] = [
                    'tone' => 'neutral', 'icon' => 'camera',
                    'title' => $withoutPhotos.' '.Str::plural('student', $withoutPhotos).' without a passport photo',
                    'body' => 'Photos go on ID cards and the student record. Add them class by class.',
                    'url' => route('school.id-cards.index'),
                ];
            }
        }

        return $attention;
    }

    /**
     * @param  array<string, mixed>|null  $today
     * @param  array<string, mixed>|null  $fees
     * @param  array<string, mixed>|null  $results
     * @return list<array{label: string, value: string, hint: ?string, icon: string, tone: string, url?: string}>
     */
    private function officeStats(int $studentCount, int $staffCount, ?AcademicSession $session, ?array $today, ?array $fees, ?array $results): array
    {
        return array_values(array_filter([
            ['label' => 'Students', 'value' => number_format($studentCount), 'hint' => $session ? "Enrolled in {$session->name}" : 'No session yet', 'icon' => 'graduation-cap', 'tone' => 'brand', 'url' => route('school.students.index')],
            $today ? [
                'label' => 'In school today', 'value' => $today['rate'] !== null ? $today['rate'].'%' : '—', 'icon' => 'clipboard-check', 'tone' => 'naija', 'url' => route('school.attendance.index'),
                'hint' => $today['open'] ? $today['taken'].' of '.$today['classes'].' registers taken · '.$today['absent'].' absent' : 'No school today',
            ] : ['label' => 'Staff', 'value' => number_format($staffCount), 'hint' => 'Active staff', 'icon' => 'briefcase', 'tone' => 'naija'],
            $fees ? [
                'label' => 'Fees collected this term', 'value' => Money::format($fees['collected']), 'icon' => 'banknote-arrow-down', 'tone' => 'gold', 'url' => route('school.payments.index'),
                'hint' => $fees['rate'] !== null ? $fees['rate'].'% of '.Money::format($fees['billed']).' billed' : 'No invoices yet',
            ] : null,
            $results ? [
                'label' => 'Scores entered', 'value' => $results['rate'] !== null ? $results['rate'].'%' : '—', 'icon' => 'file-pen-line', 'tone' => 'sky', 'url' => route('school.results.index'),
                'hint' => $results['scored'].' of '.$results['subjects'].' class subjects · '.$results['published'].' classes published',
            ] : ['label' => 'Classes', 'value' => number_format(ClassArm::query()->count()), 'hint' => 'This session', 'icon' => 'layers', 'tone' => 'sky'],
        ]));
    }

    /**
     * @param  array<string, mixed>|null  $fees
     * @return list<array{label: string, value: string, hint: ?string, icon: string, tone: string, url?: string}>
     */
    private function bursarStats(?array $fees): array
    {
        $fees ??= ['today' => 0, 'collected' => 0, 'billed' => 0, 'rate' => null, 'outstanding' => 0, 'debtors' => 0];

        return [
            ['label' => 'Collected today', 'value' => Money::format($fees['today']), 'hint' => now()->format('l j F'), 'icon' => 'banknote-arrow-down', 'tone' => 'naija', 'url' => route('school.payments.index')],
            ['label' => 'Collected this term', 'value' => Money::format($fees['collected']), 'hint' => $fees['rate'] !== null ? $fees['rate'].'% of '.Money::format($fees['billed']).' billed' : 'No invoices yet', 'icon' => 'wallet', 'tone' => 'brand', 'url' => route('school.invoices.index')],
            ['label' => 'Outstanding', 'value' => Money::format($fees['outstanding']), 'hint' => 'All terms', 'icon' => 'receipt', 'tone' => 'gold', 'url' => route('school.invoices.index', ['status' => 'unpaid'])],
            ['label' => 'Debtors', 'value' => number_format($fees['debtors']), 'hint' => 'Students owing fees', 'icon' => 'hand-coins', 'tone' => 'sky', 'url' => route('school.debtors.index')],
        ];
    }

    /**
     * @return array{in: int, out: int, term: ?int}
     */
    private function cashflow(?Term $term): array
    {
        $period = CashflowPeriod::forTerm($term);

        return ['in' => $period->income(), 'out' => $period->spending(), 'term' => $term?->id];
    }

    /**
     * A teacher's own numbers rather than the whole school's.
     *
     * @return list<array{label: string, value: string, hint: string, icon: string, tone: string}>
     */
    private function teacherStats(Staff $staff, ?AcademicSession $session): array
    {
        $armIds = $staff->classArmIds();
        $myStudents = Enrolment::query()->where('academic_session_id', $session?->id)->whereIn('class_arm_id', $armIds)->count();
        $subjects = ClassSubject::query()->where('teacher_id', $staff->id)->with('subject')->get();
        $formClass = $staff->formClasses()->with('level')->first();

        return [
            ['label' => 'My students', 'value' => number_format($myStudents), 'hint' => 'Across all your classes', 'icon' => 'graduation-cap', 'tone' => 'brand'],
            ['label' => 'Classes I teach', 'value' => (string) count($armIds), 'hint' => $subjects->count().' class subjects', 'icon' => 'layers', 'tone' => 'sky'],
            ['label' => 'Subjects', 'value' => (string) $subjects->pluck('subject_id')->unique()->count(), 'hint' => $subjects->pluck('subject.name')->unique()->take(2)->join(', ') ?: 'None assigned yet', 'icon' => 'book-open', 'tone' => 'naija'],
            ['label' => 'Form class', 'value' => $formClass?->displayName() ?? '—', 'hint' => $formClass ? 'You mark its register' : 'Not a form teacher', 'icon' => 'users', 'tone' => 'gold'],
        ];
    }

    /**
     * @return list<array{title: string, body: string, icon: string, done: bool, route: string}>
     */
    private function setupSteps(?string $address, ?string $phone, ?AcademicSession $session, int $classCount, int $studentCount, int $staffCount): array
    {
        return [
            ['title' => 'Add your school details', 'body' => 'Logo, motto, address and phone for report cards, receipts and ID cards.', 'icon' => 'school', 'done' => filled($address) && filled($phone), 'route' => 'school.profile.edit'],
            ['title' => 'Set up the session and terms', 'body' => 'Create the session with First, Second and Third Term dates.', 'icon' => 'calendar-range', 'done' => $session !== null, 'route' => 'school.sessions.index'],
            ['title' => 'Create classes and subjects', 'body' => 'Nursery to SS 3, arms like JSS 1A, and the subjects each class takes.', 'icon' => 'layers', 'done' => $classCount > 0, 'route' => 'school.classes.index'],
            ['title' => 'Bring in your students', 'body' => 'Enrol students with their parents’ contact details.', 'icon' => 'graduation-cap', 'done' => $studentCount > 0, 'route' => 'school.students.index'],
            ['title' => 'Add your staff', 'body' => 'Give the principal, bursar and teachers their own logins.', 'icon' => 'user-plus', 'done' => $staffCount > 1, 'route' => 'school.staff.index'],
        ];
    }

    /**
     * Subjects this teacher teaches, grouped by class.
     *
     * @return Collection<int, array{arm: ClassArm, subjects: array<int, string>}>
     */
    private function myClasses(Staff $staff): Collection
    {
        $teaches = fn ($query) => $query->where('teacher_id', $staff->id);

        return ClassArm::query()
            ->whereHas('classSubjects', $teaches)
            ->with(['level', 'classSubjects' => fn ($query) => $teaches($query)->with('subject')])
            ->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->map(fn (ClassArm $arm): array => [
                'arm' => $arm,
                'subjects' => $arm->classSubjects->map(fn (ClassSubject $classSubject): string => $classSubject->subject->name)->sort()->values()->all(),
            ])
            ->values();
    }
}
