<?php

namespace App\Http\Controllers\School;

use App\Actions\People\PromoteStudents;
use App\Enums\EnrolmentStatus;
use App\Enums\PromotionDecision;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Term;
use App\Models\TermReport;
use App\Support\Results\PromotionRules;
use App\Support\Results\ResultCalculator;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * End of session: move every class into the next session, class by class,
 * using the decisions on the third-term report cards.
 */
class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $from = $this->fromSession($request);
        $to = $this->nextSession($from);
        $finalTerm = $this->finalTerm($from);

        $arms = ClassArm::query()
            ->with('level')
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $from->id)->where('status', '!=', EnrolmentStatus::Withdrawn)])
            ->get()
            ->filter(fn (ClassArm $arm) => $arm->enrolments_count > 0)
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();

        $enrolments = Enrolment::query()->where('academic_session_id', $from->id)->get(['id', 'student_id', 'class_arm_id', 'status']);
        $decided = TermReport::query()->where('term_id', $finalTerm->id)->whereNotNull('promotion_decision')->pluck('enrolment_id')->flip();
        $placed = $to ? Enrolment::query()->where('academic_session_id', $to->id)->pluck('student_id')->flip() : collect();

        return view('school.promotion.index', [
            'from' => $from,
            'to' => $to,
            'sessions' => AcademicSession::query()->orderByDesc('starts_on')->pluck('name', 'id'),
            'arms' => $arms,
            'progress' => $enrolments->groupBy('class_arm_id')->map(fn (Collection $rows) => [
                'decided' => $rows->filter(fn (Enrolment $enrolment) => $decided->has($enrolment->id))->count(),
                'done' => $rows->filter(fn (Enrolment $enrolment) => $placed->has($enrolment->student_id) || $enrolment->status === EnrolmentStatus::Graduated)->count(),
            ]),
        ]);
    }

    public function show(Request $request, ClassArm $classArm, ResultCalculator $calculator): View
    {
        $from = $this->fromSession($request);
        $to = $this->nextSession($from) ?? abort(404);
        $finalTerm = $this->finalTerm($from);
        $classArm->load('level');

        $session = $calculator->sessionSoFar($classArm, $finalTerm);
        $decisions = TermReport::query()->where('term_id', $finalTerm->id)->pluck('promotion_decision', 'enrolment_id');
        $finalClass = $classArm->level->isFinal();
        $passMark = PromotionRules::passMark();

        $arms = ClassArm::query()->with(['level', 'stream'])->get()->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name));
        $nextLevel = $classArm->level->nextLevel();
        $upArm = $nextLevel ? ($arms->first(fn (ClassArm $arm) => $arm->class_level_id === $nextLevel->id && $arm->name === $classArm->name)
            ?? $arms->first(fn (ClassArm $arm) => $arm->class_level_id === $nextLevel->id)) : null;

        $enrolments = $this->classEnrolments($classArm, $from);
        $placed = Enrolment::query()->where('academic_session_id', $to->id)->whereIn('student_id', $enrolments->pluck('student_id'))->pluck('class_arm_id', 'student_id');

        return view('school.promotion.show', [
            'arm' => $classArm,
            'from' => $from,
            'to' => $to,
            'finalTerm' => $finalTerm,
            'upArm' => $upArm,
            'armOptions' => $arms->mapWithKeys(fn (ClassArm $arm) => [$arm->id => $arm->displayName()])->all(),
            'decisionOptions' => collect(PromotionDecision::cases())->mapWithKeys(fn (PromotionDecision $decision) => [$decision->value => $decision->label()])->all(),
            'rows' => $enrolments->map(function (Enrolment $enrolment) use ($session, $decisions, $finalClass, $passMark, $placed) {
                $average = $session[$enrolment->id]['average'] ?? null;
                $recorded = $decisions[$enrolment->id] ?? null;
                $suggested = PromotionRules::suggest($average, $finalClass, $passMark);

                return [
                    'enrolment' => $enrolment,
                    'average' => $average,
                    'position' => $session[$enrolment->id]['position'] ?? null,
                    'recorded' => $recorded,
                    'decision' => $recorded ?? $suggested ?? ($finalClass ? PromotionDecision::Graduated : PromotionDecision::Promoted),
                    'placed_arm_id' => $placed[$enrolment->student_id] ?? null,
                ];
            }),
        ]);
    }

    public function store(Request $request, ClassArm $classArm, PromoteStudents $promote): RedirectResponse
    {
        $from = $this->fromSession($request);
        $to = $this->nextSession($from) ?? abort(404);
        $classArm->load('level');
        $enrolmentIds = $this->classEnrolments($classArm, $from)->pluck('id')->all();

        $data = $request->validate([
            'students' => ['required', 'array'],
            'students.*.decision' => ['required', Rule::enum(PromotionDecision::class)],
            'students.*.class_arm_id' => ['nullable', 'required_unless:students.*.decision,graduated', TenantRule::exists('class_arms')],
        ], [
            'students.*.class_arm_id.required_unless' => 'Choose the class each student moves into.',
        ]);

        $placements = [];

        foreach ($data['students'] as $enrolmentId => $row) {
            if (! in_array((int) $enrolmentId, $enrolmentIds, true)) {
                continue;
            }

            $decision = PromotionDecision::from($row['decision']);
            $placements[(int) $enrolmentId] = [
                'decision' => $decision,
                'class_arm_id' => $decision === PromotionDecision::Graduated ? null : (int) $row['class_arm_id'],
            ];
        }

        $counts = $promote->handle($classArm, $from, $to, $placements, $request->user());

        return to_route('school.promotion.index', ['from' => $from->id])->with('status', sprintf(
            '%s moved into %s: %d up, %d repeating, %d graduated.',
            $classArm->displayName(), $to->name, $counts['up'], $counts['repeat'], $counts['graduated'],
        ));
    }

    private function fromSession(Request $request): AcademicSession
    {
        $sessionId = $request->integer('from');

        return ($sessionId ? AcademicSession::query()->find($sessionId) : AcademicSession::current()) ?? abort(404);
    }

    private function nextSession(AcademicSession $from): ?AcademicSession
    {
        return AcademicSession::query()->where('starts_on', '>', $from->starts_on)->orderBy('starts_on')->first();
    }

    private function finalTerm(AcademicSession $session): Term
    {
        return Term::query()->where('academic_session_id', $session->id)->orderByDesc('sequence')->firstOrFail();
    }

    /**
     * @return Collection<int, Enrolment>
     */
    private function classEnrolments(ClassArm $classArm, AcademicSession $from): Collection
    {
        return Enrolment::query()
            ->with('student')
            ->where('academic_session_id', $from->id)
            ->where('class_arm_id', $classArm->id)
            ->where('status', '!=', EnrolmentStatus::Withdrawn)
            ->get()
            ->sortBy(fn (Enrolment $enrolment) => $enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->values();
    }
}
