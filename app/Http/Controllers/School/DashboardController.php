<?php

namespace App\Http\Controllers\School;

use App\Enums\MembershipStatus;
use App\Enums\SchoolRole;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

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

        $studentCount = Enrolment::query()->where('academic_session_id', $session?->id)->count();
        $staffCount = Staff::query()->where('status', StaffStatus::Active)->count();
        $classCount = ClassArm::query()->count();
        $memberCount = $school->users()->wherePivot('status', MembershipStatus::Active->value)->count();

        $roles = $user->getRoleNames()
            ->map(fn (string $role): string => SchoolRole::tryFrom($role)?->label() ?? $role)
            ->whenEmpty(fn ($roles) => $roles->push($user->is_platform_admin ? 'Platform admin' : 'Member'));

        $stats = $user->can('students.view-all') || $staff === null
            ? [
                ['label' => 'Students', 'value' => number_format($studentCount), 'hint' => $session ? "Enrolled in {$session->name}" : 'No session yet', 'icon' => 'graduation-cap', 'tone' => 'brand'],
                ['label' => 'Staff', 'value' => number_format($staffCount), 'hint' => number_format($memberCount).' people can sign in', 'icon' => 'briefcase', 'tone' => 'naija'],
                ['label' => 'Classes', 'value' => number_format($classCount), 'hint' => number_format(Subject::query()->count()).' subjects', 'icon' => 'layers', 'tone' => 'sky'],
                ['label' => 'Parents', 'value' => number_format(Guardian::query()->count()), 'hint' => 'With portal access', 'icon' => 'users', 'tone' => 'gold'],
            ]
            : $this->teacherStats($staff, $session);

        return view('school.dashboard', [
            'school' => $school,
            'roles' => $roles,
            'term' => $term,
            'stats' => $stats,
            'genderSplit' => Student::query()->where('status', StudentStatus::Active)->selectRaw('gender, count(*) as total')->groupBy('gender')->pluck('total', 'gender'),
            'classSizes' => $this->classSizes($session),
            'myClasses' => $staff ? $this->myClasses($staff) : collect(),
            'formClasses' => $staff ? $staff->formClasses()->with('level')->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session?->id)])->get() : collect(),
            'recentActivity' => $user->can('activity.view')
                ? Activity::query()->where('school_id', $school->id)->with('causer')->latest()->limit(6)->get()
                : collect(),
            'setupSteps' => [
                ['title' => 'Add your school details', 'body' => 'Logo, motto, address and signatures for report cards and receipts.', 'icon' => 'school', 'done' => filled($school->address) && filled($school->phone), 'route' => 'school.profile.edit'],
                ['title' => 'Set up the session and terms', 'body' => 'Create the session with First, Second and Third Term dates.', 'icon' => 'calendar-range', 'done' => $session !== null, 'route' => 'school.sessions.index'],
                ['title' => 'Create classes and subjects', 'body' => 'Nursery to SS 3, arms like JSS 1A, and the subjects each class takes.', 'icon' => 'layers', 'done' => $classCount > 0, 'route' => 'school.classes.index'],
                ['title' => 'Bring in your students', 'body' => 'Enrol students with their parents’ contact details.', 'icon' => 'graduation-cap', 'done' => $studentCount > 0, 'route' => 'school.students.index'],
                ['title' => 'Add your staff', 'body' => 'Give the principal, bursar and teachers their own logins.', 'icon' => 'user-plus', 'done' => $staffCount > 1, 'route' => 'school.staff.index'],
            ],
        ]);
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
     * Largest classes first, for the class-size chart.
     *
     * @return Collection<int, array{name: string, count: int}>
     */
    private function classSizes(?AcademicSession $session): Collection
    {
        if ($session === null) {
            return collect();
        }

        return ClassArm::query()
            ->with('level')
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session->id)])
            ->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->map(fn (ClassArm $arm) => ['name' => $arm->displayName(), 'count' => (int) $arm->enrolments_count])
            ->values();
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
