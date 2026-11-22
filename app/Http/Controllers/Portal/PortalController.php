<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Fees\AllocatePayments;
use App\Enums\ClassworkStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\ExamPaper;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ResultPublication;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Term;
use App\Support\Attendance\AttendanceSummary;
use App\Support\Payments\Paystack;
use App\Support\Results\ResultCalculator;
use App\Support\Timetable\Timetable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The parent and student side of a school: a parent sees their children,
 * a student sees their own class, subjects and teachers.
 */
class PortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $guardian = $user->guardianRecord();
        $student = $user->studentRecord();

        abort_if($guardian === null && $student === null, 404);

        $term = Term::current();

        if ($guardian) {
            $children = $guardian->students()
                ->with(['currentEnrolment.classArm' => fn ($query) => $query->with(['level', 'stream', 'formTeacher'])])
                ->orderBy('first_name')
                ->get();

            return view('portal.parent', [
                'guardian' => $guardian,
                'children' => $children,
                'balances' => $children->mapWithKeys(fn (Student $child) => [$child->id => AllocatePayments::balance($child)]),
                'term' => $term,
                'latestNotices' => $this->latestNotices($request),
                'attendance' => $term
                    ? AttendanceSummary::forTerm($term, $children->map(fn (Student $child) => $child->currentEnrolment?->id)->filter()->values()->all())
                    : [],            ]);
        }

        return $this->studentView($student, $term, ownProfile: true);
    }

    public function child(Request $request, Student $student): View
    {
        Gate::authorize('view', $student);
        abort_unless($request->user()->guardianRecord()?->students()->whereKey($student->id)->exists(), 404);

        return $this->studentView($student, Term::current(), ownProfile: false);
    }

    /**
     * @return Collection<int, Announcement>
     */
    private function latestNotices(Request $request): Collection
    {
        $user = $request->user();

        return Announcement::query()->visibleTo($user)->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])->noticeBoardOrder()->limit(3)->get();
    }

    /**
     * This term's register and classwork for the student, for both portals.
     *
     * @return array<string, mixed>
     */
    private function attendanceAndWork(Student $student, ?Term $term): array
    {
        $enrolment = $student->currentEnrolment;
        $subjectIds = $enrolment ? ClassSubject::query()->where('class_arm_id', $enrolment->class_arm_id)->pluck('id') : collect();
        $lessons = $enrolment ? Timetable::forClass($enrolment->classArm) : collect();

        return [
            'latestNotices' => $this->latestNotices(request()),
            'examPapers' => $term && $enrolment
                ? ExamPaper::query()->with(['subject', 'invigilator'])->where('term_id', $term->id)->where('class_level_id', $enrolment->classArm->class_level_id)->orderBy('starts_at')->get()
                : collect(),
            'periods' => Timetable::periods(),
            'timetable' => Timetable::grid($lessons),
            'todayLessons' => Timetable::today($lessons),
            'attendance' => $term && $enrolment ? AttendanceSummary::forTerm($term, [$enrolment->id])[$enrolment->id] : null,
            'recentAttendance' => $enrolment ? AttendanceRecord::query()->where('enrolment_id', $enrolment->id)->latest('date')->limit(15)->get() : collect(),
            'assignments' => Assignment::query()->with('classSubject.subject')->whereIn('class_subject_id', $subjectIds)
                ->where('status', ClassworkStatus::Published)->latest('due_at')->limit(20)->get(),
            'submissions' => AssignmentSubmission::query()->where('student_id', $student->id)->get()->keyBy('assignment_id'),
            'quizzes' => Quiz::query()->with('classSubject.subject')->whereIn('class_subject_id', $subjectIds)
                ->where('status', ClassworkStatus::Published)->latest()->limit(20)->get(),
            'quizAttempts' => QuizAttempt::query()->where('student_id', $student->id)->get()->keyBy('quiz_id'),
        ];
    }

    private function studentView(Student $student, ?Term $term, bool $ownProfile): View
    {
        $student->load([
            'guardians',
            'currentEnrolment.classArm' => fn ($query) => $query->with([
                'level', 'stream', 'formTeacher',
                'classSubjects' => fn ($query) => $query->with(['subject', 'teacher']),
            ]),
        ]);

        $classmates = $student->currentEnrolment
            ? $student->currentEnrolment->classArm->enrolments()->where('academic_session_id', $student->currentEnrolment->academic_session_id)->count()
            : 0;

        // Only results the school has published for the student's class that term.
        $publishedResults = [];
        $enrolments = $student->enrolments()->with('classArm.level')->get()->keyBy('academic_session_id');

        ResultPublication::query()
            ->whereIn('class_arm_id', $enrolments->pluck('class_arm_id'))
            ->get()
            ->each(function (ResultPublication $publication) use ($enrolments, $student, &$publishedResults): void {
                $resultTerm = Term::query()->with('academicSession')->find($publication->term_id);
                $enrolment = $enrolments->get($resultTerm?->academic_session_id);

                if ($resultTerm === null || $enrolment === null || $enrolment->class_arm_id !== $publication->class_arm_id) {
                    return;
                }

                $classResults = app(ResultCalculator::class)->forClass($enrolment->classArm, $resultTerm);
                $row = $classResults->forStudent($student->id);

                if ($row && $row['subject_count'] > 0) {
                    $publishedResults[] = ['term' => $resultTerm, 'arm' => $enrolment->classArm, 'row' => $row, 'size' => $classResults->classSize];
                }
            });

        return view('portal.student', [
            'student' => $student,
            'term' => $term,
            'classmates' => $classmates,
            'ownProfile' => $ownProfile,
            'invoices' => $student->invoices()->with('term.academicSession')->where('status', '!=', InvoiceStatus::Void)->latest('issued_at')->get(),
            'payments' => $student->payments()->where('status', PaymentStatus::Confirmed)->latest('paid_at')->limit(10)->get(),
            'balance' => AllocatePayments::balance($student),
            'canPayOnline' => ! $ownProfile
                && app(Paystack::class)->isConfigured()
                && filled(SchoolSetting::valueFor('paystack_subaccount')),
            ...$this->attendanceAndWork($student, $term),
            'publishedResults' => collect($publishedResults)->sortByDesc(fn (array $result) => $result['term']->starts_on)->values(),
        ]);
    }
}
