<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Fees\AllocatePayments;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ResultPublication;
use App\Models\Student;
use App\Models\Term;
use App\Support\Payments\Paystack;
use App\Support\Results\ResultCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
            ]);
        }

        return $this->studentView($student, $term, ownProfile: true);
    }

    public function child(Request $request, Student $student): View
    {
        Gate::authorize('view', $student);
        abort_unless($request->user()->guardianRecord()?->students()->whereKey($student->id)->exists(), 404);

        return $this->studentView($student, Term::current(), ownProfile: false);
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
            'canPayOnline' => ! $ownProfile && app(Paystack::class)->isConfigured(),
            'publishedResults' => collect($publishedResults)->sortByDesc(fn (array $result) => $result['term']->starts_on)->values(),
        ]);
    }
}
