<?php

namespace App\Http\Controllers\School;

use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Staff;
use App\Models\Student;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Student and staff ID cards: pick who to print, print them on A4 card sheets,
 * and the public page the QR code on each card opens to check it is genuine.
 */
class IdCardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('students.manage') || $user->can('staff.manage'), 403);

        $session = AcademicSession::current();
        $arms = $this->arms($session);
        $arm = $arms->firstWhere('ulid', $request->query('class')) ?? $arms->first();

        return view('school.id-cards.index', [
            'session' => $session,
            'arms' => $arms,
            'arm' => $arm,
            'students' => $arm ? $this->studentsIn($arm, $session) : collect(),
            'staff' => $user->can('staff.manage')
                ? Staff::query()->where('status', StaffStatus::Active)->orderBy('last_name')->orderBy('first_name')->get()
                : collect(),
            'canStudents' => $user->can('students.manage'),
            'canStaff' => $user->can('staff.manage'),
        ]);
    }

    public function students(Request $request): View
    {
        $session = AcademicSession::current();
        $arm = ClassArm::query()->with('level')->where('ulid', (string) $request->query('class'))->first();
        $students = $arm ? $this->studentsIn($arm, $session) : collect();

        if ($request->filled('only')) {
            $students = $students->whereIn('ulid', (array) $request->query('only'))->values();
        }

        return view('school.id-cards.print', [
            'kind' => 'student',
            'session' => $session,
            'cards' => $students->map(fn (Student $student): array => [
                'ulid' => $student->ulid,
                'person' => $student,
                'name' => $student->fullName(),
                'number' => $student->admission_no,
                'role' => 'Student',
                'lines' => array_filter([
                    'Class' => $arm?->displayName(),
                    'Gender' => $student->gender->label(),
                    'Blood group' => $student->blood_group ? $student->blood_group.($student->genotype ? ' · '.$student->genotype : '') : null,
                ]),
                'emergency' => ($guardian = $student->guardians->first()) ? $guardian->fullName().' · '.$guardian->phone : null,
            ])->values(),
            'title' => ($arm?->displayName() ?? 'Student').' ID cards',
            'backs' => $request->boolean('backs', true),
        ]);
    }

    public function staff(Request $request): View
    {
        $staff = Staff::query()->where('status', StaffStatus::Active)->orderBy('last_name')->orderBy('first_name')->get();

        if ($request->filled('only')) {
            $staff = $staff->whereIn('ulid', (array) $request->query('only'))->values();
        }

        return view('school.id-cards.print', [
            'kind' => 'staff',
            'session' => AcademicSession::current(),
            'cards' => $staff->map(fn (Staff $member): array => [
                'ulid' => $member->ulid,
                'person' => $member,
                'name' => $member->fullName(),
                'number' => $member->staff_no,
                'role' => $member->designation ?: 'Staff',
                'lines' => array_filter([
                    'Department' => $member->department,
                    'Phone' => $member->phone,
                ]),
                'emergency' => null,
            ])->values(),
            'title' => 'Staff ID cards',
            'backs' => $request->boolean('backs', true),
        ]);
    }

    /**
     * Public: the page a card's QR code opens. Shows only what is printed on the card, and whether it is still valid.
     */
    public function verify(CurrentSchool $currentSchool, string $kind, string $ulid): View
    {
        $person = $kind === 'student'
            ? Student::query()->with('currentEnrolment.classArm.level')->where('ulid', $ulid)->first()
            : Staff::query()->where('ulid', $ulid)->first();

        $valid = match (true) {
            $person instanceof Student => $person->status === StudentStatus::Active && $person->currentEnrolment !== null,
            $person instanceof Staff => $person->status === StaffStatus::Active,
            default => false,
        };

        return view('school.id-cards.verify', [
            'school' => $currentSchool->getOrFail(),
            'kind' => $kind,
            'person' => $person,
            'valid' => $valid,
            'detail' => match (true) {
                $person instanceof Student => $person->currentEnrolment?->classArm->displayName(),
                $person instanceof Staff => $person->designation,
                default => null,
            },
        ]);
    }

    /**
     * @return Collection<int, ClassArm>
     */
    private function arms(?AcademicSession $session): Collection
    {
        return ClassArm::query()
            ->with('level')
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session->id ?? 0)])
            ->get()
            ->filter(fn (ClassArm $arm) => $arm->enrolments_count > 0)
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsIn(ClassArm $arm, ?AcademicSession $session): Collection
    {
        return Enrolment::query()
            ->with(['student' => fn ($query) => $query->with('guardians')])
            ->where('class_arm_id', $arm->id)
            ->where('academic_session_id', $session->id ?? 0)
            ->get()
            ->map(fn (Enrolment $enrolment) => $enrolment->student)
            ->filter()
            ->sortBy(fn (Student $student) => $student->last_name.' '.$student->first_name)
            ->values();
    }
}
