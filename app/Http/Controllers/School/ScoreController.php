<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Score;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The scorebook: teachers enter CA and exam scores for the subjects they teach.
 */
class ScoreController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $term = Term::current();
        $seesAll = self::seesAllSubjects($user);
        $staffId = $user->staffRecord()?->id;

        $classSubjects = ClassSubject::query()
            ->with(['subject', 'teacher', 'classArm.level'])
            ->when(! $seesAll, fn (Builder $query) => $query->where('teacher_id', $staffId ?? 0))
            ->get()
            ->sortBy(fn (ClassSubject $classSubject) => sprintf('%03d-%s-%s', $classSubject->classArm->level->position, $classSubject->classArm->name, $classSubject->subject->name))
            ->values();

        $components = AssessmentComponent::query()->count();
        $classSizes = $term ? Enrolment::query()->where('academic_session_id', $term->academic_session_id)->selectRaw('class_arm_id, count(*) as total')->groupBy('class_arm_id')->pluck('total', 'class_arm_id') : collect();
        $entered = $term ? Score::query()->where('term_id', $term->id)->selectRaw('class_subject_id, count(*) as total')->groupBy('class_subject_id')->pluck('total', 'class_subject_id') : collect();

        return view('school.scores.index', [
            'term' => $term,
            'classSubjects' => $classSubjects,
            'progress' => $classSubjects->mapWithKeys(function (ClassSubject $classSubject) use ($components, $classSizes, $entered) {
                $expected = ($classSizes[$classSubject->class_arm_id] ?? 0) * $components;

                return [$classSubject->id => $expected === 0 ? 0 : (int) min(100, round(($entered[$classSubject->id] ?? 0) / $expected * 100))];
            }),
            'seesAll' => $seesAll,
        ]);
    }

    public function edit(Request $request, ClassSubject $classSubject): View
    {
        $this->authorizeEntry($request->user(), $classSubject);
        $term = Term::current() ?? abort(404);
        $classSubject->load(['subject', 'teacher', 'classArm.level']);

        $enrolments = Enrolment::query()
            ->where('class_arm_id', $classSubject->class_arm_id)
            ->where('academic_session_id', $term->academic_session_id)
            ->with('student')
            ->get()
            ->sortBy(fn (Enrolment $enrolment) => $enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->values();

        $scores = Score::query()
            ->where('term_id', $term->id)
            ->where('class_subject_id', $classSubject->id)
            ->get()
            ->groupBy('enrolment_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (Score $score) => [$score->assessment_component_id => $score->score]));

        return view('school.scores.edit', [
            'classSubject' => $classSubject,
            'term' => $term,
            'components' => AssessmentComponent::query()->orderBy('position')->get(),
            'rows' => $enrolments->map(fn (Enrolment $enrolment) => [
                'enrolment' => $enrolment->id,
                'name' => $enrolment->student->registerName(),
                'admission_no' => $enrolment->student->admission_no,
                'scores' => (object) ($scores[$enrolment->id] ?? collect())->all(),
            ])->all(),
            'gradeBands' => $classSubject->classArm->level->gradingScheme->bands ?? [],
        ]);
    }

    /**
     * Save a batch of changed cells. A null score clears the cell.
     */
    public function update(Request $request, ClassSubject $classSubject): JsonResponse
    {
        $this->authorizeEntry($request->user(), $classSubject);
        $term = Term::current() ?? abort(404);

        if ($term->isClosed()) {
            throw ValidationException::withMessages(['scores' => "{$term->name} is closed. Ask an admin to reopen it before changing scores."]);
        }

        $components = AssessmentComponent::query()->pluck('max_score', 'id');
        $enrolmentIds = Enrolment::query()
            ->where('class_arm_id', $classSubject->class_arm_id)
            ->where('academic_session_id', $term->academic_session_id)
            ->pluck('id')
            ->all();

        $data = $request->validate([
            'scores' => ['required', 'array', 'max:2000'],
            'scores.*.enrolment' => ['required', 'integer', Rule::in($enrolmentIds)],
            'scores.*.component' => ['required', 'integer', Rule::in($components->keys()->all())],
            'scores.*.score' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($data['scores'] as $index => $cell) {
            if ($cell['score'] !== null && $cell['score'] > $components[$cell['component']]) {
                throw ValidationException::withMessages(["scores.{$index}.score" => "A score can’t be more than {$components[$cell['component']]}."]);
            }
        }

        DB::transaction(function () use ($data, $term, $classSubject, $request): void {
            foreach ($data['scores'] as $cell) {
                $keys = [
                    'term_id' => $term->id,
                    'enrolment_id' => $cell['enrolment'],
                    'class_subject_id' => $classSubject->id,
                    'assessment_component_id' => $cell['component'],
                ];

                if ($cell['score'] === null) {
                    Score::query()->where($keys)->delete();
                } else {
                    Score::query()->updateOrCreate($keys, ['score' => round((float) $cell['score'], 1), 'entered_by' => $request->user()->id]);
                }
            }
        });

        return response()->json(['saved' => count($data['scores'])]);
    }

    public static function seesAllSubjects(User $user): bool
    {
        return $user->can('results.approve') || $user->can('academics.manage');
    }

    private function authorizeEntry(User $user, ClassSubject $classSubject): void
    {
        $isTheTeacher = $classSubject->teacher_id !== null && $classSubject->teacher_id === $user->staffRecord()?->id;

        abort_unless($user->can('scores.enter') && ($isTheTeacher || self::seesAllSubjects($user)), 403);
    }
}
