<?php

namespace App\Http\Controllers\School;

use App\Enums\CommentAudience;
use App\Enums\PromotionDecision;
use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\ReportTrait;
use App\Models\Term;
use App\Models\TermReport;
use App\Models\User;
use App\Support\Results\CommentBank;
use App\Support\Results\PromotionRules;
use App\Support\Results\ResultCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The form teacher's console: attendance, trait ratings and comments for each
 * student's report card. Principals add their own comment and, in the third
 * term, the promotion decision.
 */
class ReportRemarksController extends Controller
{
    public function edit(Request $request, ClassArm $classArm, ResultCalculator $calculator, CommentBank $comments): View
    {
        $user = $request->user();
        $this->authorizeRemarks($user, $classArm);
        $term = $this->selectedTerm($request);
        $classArm->load(['level', 'formTeacher']);

        $results = $calculator->forClass($classArm, $term);
        $isFinalTerm = $term->isFinal();
        $cumulative = $isFinalTerm ? $calculator->sessionSoFar($classArm, $term, $results) : [];
        $reports = TermReport::query()->where('term_id', $term->id)->get()->keyBy('enrolment_id');
        $traits = ReportTrait::query()->orderBy('category')->orderBy('position')->get();
        $finalClass = $classArm->level->isFinal();
        $passMark = PromotionRules::passMark();

        $rows = $results->students->map(function (array $row) use ($reports, $comments, $cumulative, $finalClass, $passMark, $traits) {
            $enrolment = $row['enrolment'];
            $report = $reports->get($enrolment->id);
            $firstName = $row['student']->first_name;
            $cumulativeAverage = $cumulative[$enrolment->id]['average'] ?? null;

            return [
                'enrolment' => $enrolment->id,
                'name' => $row['student']->registerName(),
                'admission_no' => $row['student']->admission_no,
                'average' => $row['average'],
                'position' => isset($row['position']) ? ResultCalculator::ordinal($row['position']) : null,
                'cumulative' => $cumulativeAverage,
                'days_present' => $report?->days_present,
                'traits' => (object) ($report->traits ?? []),
                'teacher_comment' => $report->teacher_comment ?? '',
                'principal_comment' => $report->principal_comment ?? '',
                'promotion_decision' => $report?->promotion_decision?->value,
                'suggested_decision' => PromotionRules::suggest($cumulativeAverage, $finalClass, $passMark)?->value,
                'scores_complete' => $row['complete'],
                // A part-way average (say CA1 and CA2 only) would suggest the wrong comment.
                'suggestions' => [
                    'teacher' => $row['complete'] ? $comments->optionsFor(CommentAudience::FormTeacher, $row['average'], $firstName) : [],
                    'principal' => $row['complete'] ? $comments->optionsFor(CommentAudience::Principal, $row['average'], $firstName) : [],
                ],
                'complete' => $report?->isComplete($traits->count()) ?? false,
            ];
        })->values();

        return view('school.results.remarks', [
            'arm' => $classArm,
            'term' => $term,
            'terms' => Term::options(),
            'rows' => $rows,
            'traits' => $traits,
            'isFinalTerm' => $isFinalTerm,
            'canApprove' => $user->can('results.approve'),
            'decisions' => collect(PromotionDecision::cases())->mapWithKeys(fn (PromotionDecision $decision) => [$decision->value => $decision->label()])->all(),
        ]);
    }

    /**
     * Save one student's remarks (JSON, from the slide-in panel).
     */
    public function update(Request $request, ClassArm $classArm, Enrolment $enrolment): JsonResponse
    {
        $user = $request->user();
        $this->authorizeRemarks($user, $classArm);
        $term = $this->selectedTerm($request);
        $this->ensureOpen($term);
        abort_unless($enrolment->class_arm_id === $classArm->id && $enrolment->academic_session_id === $term->academic_session_id, 404);

        $canApprove = $user->can('results.approve');
        $traitIds = ReportTrait::query()->pluck('id')->all();

        $data = $request->validate([
            'days_present' => ['nullable', 'integer', 'min:0', 'max:'.($term->school_days ?? 366)],
            'traits' => ['nullable', 'array'],
            'traits.*' => ['nullable', 'integer', 'between:1,5'],
            'teacher_comment' => ['nullable', 'string', 'max:500'],
            'principal_comment' => ['nullable', 'string', 'max:500'],
            'promotion_decision' => ['nullable', Rule::enum(PromotionDecision::class)],
        ], [
            'days_present.max' => 'The school only opened :max times this term.',
        ]);

        $ratings = [];

        foreach ($data['traits'] ?? [] as $traitId => $rating) {
            if ($rating !== null && in_array((int) $traitId, $traitIds, true)) {
                $ratings[(int) $traitId] = (int) $rating;
            }
        }

        $attributes = [
            'days_present' => $data['days_present'] ?? null,
            'traits' => $ratings === [] ? null : $ratings,
            'teacher_comment' => $data['teacher_comment'] ?? null,
            'updated_by' => $user->id,
        ];

        if ($canApprove) {
            $attributes['principal_comment'] = $data['principal_comment'] ?? null;

            if ($term->isFinal()) {
                $attributes['promotion_decision'] = $data['promotion_decision'] ?? null;
            }
        }

        $report = TermReport::query()->updateOrCreate(['term_id' => $term->id, 'enrolment_id' => $enrolment->id], $attributes);

        return response()->json(['complete' => $report->isComplete(count($traitIds))]);
    }

    /**
     * Fill every empty comment (and, in the third term, every empty decision) from the comment bank.
     */
    public function autofill(Request $request, ClassArm $classArm, ResultCalculator $calculator, CommentBank $comments): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRemarks($user, $classArm);
        $term = $this->selectedTerm($request);
        $this->ensureOpen($term);
        $classArm->load('level');

        $canApprove = $user->can('results.approve');
        $results = $calculator->forClass($classArm, $term);
        $cumulative = $canApprove && $term->isFinal() ? $calculator->sessionSoFar($classArm, $term, $results) : [];
        $reports = TermReport::query()->where('term_id', $term->id)->get()->keyBy('enrolment_id');
        $finalClass = $classArm->level->isFinal();
        $passMark = PromotionRules::passMark();
        $filled = 0;
        $waiting = 0;

        foreach ($results->students as $row) {
            if (! $row['complete']) {
                $waiting += $row['average'] === null ? 0 : 1;

                continue;
            }

            $enrolmentId = $row['enrolment']->id;
            $report = $reports->get($enrolmentId);
            $firstName = $row['student']->first_name;
            $changes = array_filter([
                'teacher_comment' => blank($report?->teacher_comment)
                    ? $comments->suggest(CommentAudience::FormTeacher, $row['average'], $firstName, $enrolmentId)
                    : null,
                'principal_comment' => $canApprove && blank($report?->principal_comment)
                    ? $comments->suggest(CommentAudience::Principal, $row['average'], $firstName, $enrolmentId)
                    : null,
                'promotion_decision' => $cumulative !== [] && $report?->promotion_decision === null
                    ? PromotionRules::suggest($cumulative[$enrolmentId]['average'] ?? null, $finalClass, $passMark)
                    : null,
            ]);

            if ($changes !== []) {
                TermReport::query()->updateOrCreate(['term_id' => $term->id, 'enrolment_id' => $enrolmentId], [...$changes, 'updated_by' => $user->id]);
                $filled++;
            }
        }

        activity('results')->performedOn($classArm)->log("filled report comments for {$classArm->displayName()} {$term->name}");

        $status = $filled === 0
            ? 'No empty comments to fill.'
            : "Comments filled for {$filled} ".str('student')->plural($filled).'. Read through and adjust any that need a personal touch.';

        if ($waiting > 0) {
            $status .= " {$waiting} ".str('student')->plural($waiting).' skipped until all their scores are in.';
        }

        return back()->with('status', $status);
    }

    public static function canRemark(User $user, ClassArm $classArm): bool
    {
        if ($user->can('results.approve') || $user->can('academics.manage')) {
            return true;
        }

        return $classArm->form_teacher_id !== null && $classArm->form_teacher_id === $user->staffRecord()?->id;
    }

    private function authorizeRemarks(User $user, ClassArm $classArm): void
    {
        abort_unless(self::canRemark($user, $classArm), 403);
    }

    private function selectedTerm(Request $request): Term
    {
        $termId = $request->integer('term');

        return ($termId ? Term::query()->with('academicSession')->find($termId) : Term::current()) ?? abort(404);
    }

    private function ensureOpen(Term $term): void
    {
        if ($term->isClosed()) {
            throw ValidationException::withMessages(['term' => "{$term->name} is closed. Ask an admin to reopen it before changing report cards."]);
        }
    }
}
