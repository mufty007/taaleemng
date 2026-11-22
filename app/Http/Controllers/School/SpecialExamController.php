<?php

namespace App\Http\Controllers\School;

use App\Enums\EnrolmentStatus;
use App\Enums\SpecialExamKind;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\SpecialExam;
use App\Models\SpecialExamScore;
use App\Models\Subject;
use App\Support\Exams\SpecialExamResults;
use App\Support\Reports\EnrolmentReport;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mock exams and WAEC, NECO, JAMB, BECE and Common Entrance practice: set the papers and
 * the classes that sit them, enter the marks, rank the candidates and print result slips.
 * Kept apart from termly results, so a mock never touches a report card.
 */
class SpecialExamController extends Controller
{
    public function index(): View
    {
        return view('school.special-exams.index', [
            'exams' => SpecialExam::query()
                ->with('academicSession')
                ->withCount(['candidates', 'papers'])
                ->latest('held_on')
                ->latest('id')
                ->get(),
            'kinds' => SpecialExamKind::options(),
            'arms' => $this->armsOnRoll(),
            'subjects' => Subject::query()->orderBy('name')->pluck('name', 'id')->all(),
            'session' => AcademicSession::current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $session = AcademicSession::current();

        if ($session === null) {
            throw ValidationException::withMessages(['title' => 'Set up the current session first.'])->errorBag('exam');
        }

        $data = $request->validateWithBag('exam', [
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(SpecialExamKind::class)],
            'held_on' => ['required', 'date'],
            'pass_mark' => ['required', 'integer', 'min:1', 'max:100'],
            'max_score' => ['required', 'integer', 'min:10', 'max:400'],
            'class_arm_ids' => ['required', 'array', 'min:1'],
            'class_arm_ids.*' => ['integer', TenantRule::exists('class_arms')],
            'subject_ids' => ['required', 'array', 'min:1', 'max:20'],
            'subject_ids.*' => ['integer', TenantRule::exists('subjects')],
        ], [
            'class_arm_ids.required' => 'Choose at least one class to sit the exam.',
            'subject_ids.required' => 'Choose at least one paper.',
        ]);

        $exam = DB::transaction(function () use ($data, $session, $request): SpecialExam {
            $exam = SpecialExam::query()->create([
                'academic_session_id' => $session->id,
                'title' => $data['title'],
                'kind' => $data['kind'],
                'held_on' => $data['held_on'],
                'pass_mark' => $data['pass_mark'],
                'created_by' => $request->user()->id,
            ]);

            Subject::query()->whereIn('id', $data['subject_ids'])->orderBy('name')->get()
                ->each(fn (Subject $subject, int $index) => $exam->papers()->create([
                    'subject_id' => $subject->id,
                    'name' => $subject->name,
                    'max_score' => $data['max_score'],
                    'position' => $index + 1,
                ]));

            Enrolment::query()
                ->where('academic_session_id', $session->id)
                ->whereIn('class_arm_id', $data['class_arm_ids'])
                ->where('status', '!=', EnrolmentStatus::Withdrawn)
                ->whereHas('student')
                ->get(['student_id', 'class_arm_id'])
                ->each(fn (Enrolment $enrolment) => $exam->candidates()->create([
                    'student_id' => $enrolment->student_id,
                    'class_arm_id' => $enrolment->class_arm_id,
                ]));

            return $exam;
        });

        $candidates = $exam->candidates()->count();
        activity('academics')->performedOn($exam)->log("set up {$exam->title} for {$candidates} candidates");

        return redirect()->route('school.special-exams.scores', $exam)
            ->with('status', "{$exam->title} is set for {$candidates} ".str('candidate')->plural($candidates).'. Enter the marks as papers are marked.');
    }

    public function show(SpecialExam $specialExam): View
    {
        $results = SpecialExamResults::for($specialExam);

        return view('school.special-exams.show', [
            'exam' => $specialExam,
            'results' => $results,
            'summary' => $results->summary(),
        ]);
    }

    public function scores(Request $request, SpecialExam $specialExam): View
    {
        $results = SpecialExamResults::for($specialExam);
        $classes = $results->candidates->pluck('class')->unique()->sort()->values();
        $class = $classes->contains($request->query('class')) ? (string) $request->query('class') : null;

        return view('school.special-exams.scores', [
            'exam' => $specialExam,
            'papers' => $results->papers,
            'classes' => $classes,
            'class' => $class,
            'rows' => $results->candidates
                ->filter(fn (array $row) => $class === null || $row['class'] === $class)
                ->sortBy(fn (array $row) => $row['class'].' '.$row['student']->last_name.' '.$row['student']->first_name)
                ->values(),
        ]);
    }

    public function saveScores(Request $request, SpecialExam $specialExam): RedirectResponse
    {
        $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['array'],
            'scores.*.*' => ['nullable', 'numeric', 'min:0'],
        ], ['scores.*.*.numeric' => 'Marks must be numbers.', 'scores.*.*.min' => 'Marks can’t be below zero.']);

        $papers = $specialExam->papers()->get()->keyBy('id');
        $candidateIds = $specialExam->candidates()->pluck('id')->flip();
        $entries = [];

        foreach ((array) $request->input('scores') as $candidateId => $marks) {
            abort_unless($candidateIds->has($candidateId), 422, 'That candidate is not sitting this exam.');

            foreach ((array) $marks as $paperId => $mark) {
                $paper = $papers->get($paperId) ?? abort(422, 'That paper is not part of this exam.');

                if ($mark !== null && (float) $mark > $paper->max_score) {
                    throw ValidationException::withMessages(["scores.{$candidateId}.{$paperId}" => "{$paper->name} is out of {$paper->max_score}."]);
                }

                $entries[] = ['candidate' => (int) $candidateId, 'paper' => (int) $paperId, 'mark' => $mark === null ? null : round((float) $mark, 1)];
            }
        }

        DB::transaction(function () use ($entries, $request): void {
            foreach ($entries as $entry) {
                $keys = ['special_exam_candidate_id' => $entry['candidate'], 'special_exam_paper_id' => $entry['paper']];

                if ($entry['mark'] === null) {
                    SpecialExamScore::query()->where($keys)->delete();
                } else {
                    SpecialExamScore::query()->updateOrCreate($keys, ['score' => $entry['mark'], 'entered_by' => $request->user()->id]);
                }
            }
        });

        activity('academics')->performedOn($specialExam)->log('entered marks for '.$specialExam->title);

        return back()->with('status', 'Marks saved.');
    }

    public function publish(SpecialExam $specialExam): RedirectResponse
    {
        $specialExam->update(['published_at' => $specialExam->isPublished() ? null : now()]);

        activity('academics')->performedOn($specialExam)->log(($specialExam->isPublished() ? 'published ' : 'withdrew ').$specialExam->title.' results');

        return back()->with('status', $specialExam->isPublished()
            ? 'Results published. Students and parents can now see them on the portal.'
            : 'Results hidden from the portal again.');
    }

    public function destroy(SpecialExam $specialExam): RedirectResponse
    {
        $specialExam->delete();

        activity('academics')->log('deleted the special exam '.$specialExam->title);

        return redirect()->route('school.special-exams.index')->with('status', $specialExam->title.' and its marks were deleted.');
    }

    public function print(SpecialExam $specialExam): View
    {
        $results = SpecialExamResults::for($specialExam);

        return view('school.special-exams.print', [
            'exam' => $specialExam,
            'results' => $results,
            'summary' => $results->summary(),
        ]);
    }

    /**
     * One result slip per candidate who sat, to hand out or send home.
     */
    public function slips(SpecialExam $specialExam): View
    {
        return view('school.special-exams.slips', [
            'exam' => $specialExam,
            'results' => SpecialExamResults::for($specialExam),
        ]);
    }

    /**
     * Class arms with students this session, nursery to SS 3, for the "who sits it" picker.
     *
     * @return Collection<int, ClassArm>
     */
    private function armsOnRoll(): Collection
    {
        $session = AcademicSession::current();

        if ($session === null) {
            return collect();
        }

        return ClassArm::query()
            ->with('level')
            ->whereHas('enrolments', fn ($query) => $query->where('academic_session_id', $session->id))
            ->get()
            ->sortBy(fn (ClassArm $arm) => EnrolmentReport::armSortKey($arm))
            ->values();
    }
}
