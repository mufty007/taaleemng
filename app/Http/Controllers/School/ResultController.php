<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\ReportTrait;
use App\Models\ResultPublication;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermReport;
use App\Support\Attendance\AttendanceSummary;
use App\Support\Results\ResultCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Broadsheets, publishing results to families, and printable report cards.
 */
class ResultController extends Controller
{
    public function index(Request $request): View
    {
        $term = $this->selectedTerm($request);
        $user = $request->user();
        $armIds = $user->can('students.view-all') ? null : ($user->staffRecord()?->classArmIds() ?? []);

        $arms = ClassArm::query()
            ->with('level')
            ->when($armIds !== null, fn ($query) => $query->whereIn('id', $armIds))
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $term?->academic_session_id)])
            ->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();

        return view('school.results.index', [
            'term' => $term,
            'terms' => Term::options(),
            'arms' => $arms,
            'published' => $term ? ResultPublication::query()->where('term_id', $term->id)->pluck('published_at', 'class_arm_id') : collect(),
            'remarked' => $term ? TermReport::query()
                ->where('term_reports.term_id', $term->id)
                ->whereNotNull('term_reports.teacher_comment')
                ->join('enrolments', 'enrolments.id', '=', 'term_reports.enrolment_id')
                ->selectRaw('enrolments.class_arm_id, count(*) as total')
                ->groupBy('enrolments.class_arm_id')
                ->pluck('total', 'class_arm_id') : collect(),
            'remarkable' => $arms->filter(fn (ClassArm $arm) => ReportRemarksController::canRemark($user, $arm))->pluck('id')->flip(),
        ]);
    }

    public function show(Request $request, ClassArm $classArm, ResultCalculator $calculator): View
    {
        $this->authorizeClass($request, $classArm);
        $term = $this->selectedTerm($request) ?? abort(404);

        return view('school.results.show', [
            'results' => $calculator->forClass($classArm, $term),
            'terms' => Term::options(),
            'publication' => ResultPublication::query()->where('term_id', $term->id)->where('class_arm_id', $classArm->id)->first(),
            'canRemark' => ReportRemarksController::canRemark($request->user(), $classArm),
        ]);
    }

    public function togglePublication(Request $request, ClassArm $classArm): RedirectResponse
    {
        $term = $this->selectedTerm($request) ?? abort(404);
        $classArm->load('level');
        $existing = ResultPublication::query()->where('term_id', $term->id)->where('class_arm_id', $classArm->id)->first();

        if ($existing) {
            $existing->delete();
            activity('results')->performedOn($classArm)->log("withdrew {$classArm->displayName()} {$term->name} results");

            return back()->with('status', "{$classArm->displayName()} results are hidden from parents and students again.");
        }

        ResultPublication::query()->create([
            'term_id' => $term->id,
            'class_arm_id' => $classArm->id,
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);
        activity('results')->performedOn($classArm)->log("published {$classArm->displayName()} {$term->name} results");

        return back()->with('status', "{$classArm->displayName()} results are now visible to parents and students.");
    }

    /**
     * A printable report card. Staff see any term; families only published ones.
     */
    public function report(Request $request, Student $student, ResultCalculator $calculator): View
    {
        Gate::authorize('view', $student);
        $term = $this->selectedTerm($request) ?? abort(404);

        $enrolment = Enrolment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $term->academic_session_id)
            ->with('classArm.level', 'classArm.formTeacher')
            ->firstOrFail();

        $isFamily = ! $request->user()->isStaffInCurrentSchool();
        abort_if($isFamily && ! ResultPublication::query()->where('term_id', $term->id)->where('class_arm_id', $enrolment->class_arm_id)->exists(), 404);

        $results = $calculator->forClass($enrolment->classArm, $term);
        $isFinalTerm = $term->isFinal();

        return view('school.results.report', [
            'results' => $results,
            'row' => $results->forStudent($student->id) ?? abort(404),
            'student' => $student->load('guardians'),
            'report' => TermReport::query()->where('term_id', $term->id)->where('enrolment_id', $enrolment->id)->first(),
            'traits' => ReportTrait::query()->orderBy('category')->orderBy('position')->get(),
            'session' => $isFinalTerm ? ($calculator->sessionSoFar($enrolment->classArm, $term, $results)[$enrolment->id] ?? null) : null,
            'sessionTerms' => $isFinalTerm ? Term::query()->where('academic_session_id', $term->academic_session_id)->where('sequence', '<=', $term->sequence)->orderBy('sequence')->get() : collect(),
            'nextLevel' => $isFinalTerm ? $enrolment->classArm->level->nextLevel() : null,
            'register' => AttendanceSummary::forTerm($term, [$enrolment->id])[$enrolment->id],
            'registerDays' => AttendanceSummary::daysTaken($term, $enrolment->class_arm_id),
        ]);
    }

    private function authorizeClass(Request $request, ClassArm $classArm): void
    {
        $user = $request->user();

        abort_unless(
            $user->can('results.view') && ($user->can('students.view-all') || in_array($classArm->id, $user->staffRecord()?->classArmIds() ?? [], true)),
            403,
        );
    }

    private function selectedTerm(Request $request): ?Term
    {
        $termId = $request->integer('term');

        return $termId ? Term::query()->with('academicSession')->find($termId) : Term::current();
    }
}
