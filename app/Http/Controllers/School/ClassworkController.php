<?php

namespace App\Http\Controllers\School;

use App\Actions\Classwork\SendQuizScoresToScorebook;
use App\Enums\ClassworkStatus;
use App\Enums\QuestionType;
use App\Enums\QuizKind;
use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Term;
use App\Models\User;
use App\Support\Classwork\ClassworkAccess;
use App\Support\Files\StoredFiles;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Teachers' classwork: assignments, and online quizzes, tests and CBT exams
 * for the subjects they teach.
 */
class ClassworkController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $classSubjects = ClassworkAccess::manageable($user);
        $ids = $classSubjects->pluck('id');
        $selected = $request->integer('class_subject') ?: null;

        return view('school.classwork.index', [
            'classSubjects' => $classSubjects,
            'selected' => $selected,
            'quizzes' => Quiz::query()->with(['classSubject.subject', 'classSubject.classArm.level'])->withCount(['questions', 'attempts' => fn ($query) => $query->whereNotNull('submitted_at')])
                ->whereIn('class_subject_id', $selected ? [$selected] : $ids)->latest()->get(),
            'assignments' => Assignment::query()->with(['classSubject.subject', 'classSubject.classArm.level'])->withCount('submissions')
                ->whereIn('class_subject_id', $selected ? [$selected] : $ids)->latest('due_at')->get(),
            'kinds' => collect(QuizKind::cases())->mapWithKeys(fn (QuizKind $kind) => [$kind->value => $kind->label()])->all(),
            'subjectOptions' => $classSubjects->mapWithKeys(fn (ClassSubject $classSubject) => [$classSubject->id => $classSubject->classArm->displayName().' · '.$classSubject->subject->name])->all(),
        ]);
    }

    public function storeQuiz(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('quiz', [
            'class_subject_id' => ['required', TenantRule::exists('class_subjects')],
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(QuizKind::class)],
            'duration_minutes' => ['nullable', 'integer', 'between:1,300'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
        ]);

        $classSubject = ClassSubject::query()->findOrFail($data['class_subject_id']);
        $this->authorizeSubject($request->user(), $classSubject);

        $quiz = Quiz::query()->create([...$data, 'term_id' => $this->termId(), 'created_by' => $request->user()->id, 'status' => ClassworkStatus::Draft]);

        return to_route('school.classwork.quizzes.edit', $quiz)->with('status', 'Now add the questions, then publish it to the class.');
    }

    public function editQuiz(Request $request, Quiz $quiz): View
    {
        $this->authorizeSubject($request->user(), $quiz->classSubject);
        $quiz->load(['classSubject.subject', 'classSubject.classArm.level', 'questions']);

        return view('school.classwork.quiz-edit', [
            'quiz' => $quiz,
            'locked' => $quiz->attempts()->exists(),
            'kinds' => collect(QuizKind::cases())->mapWithKeys(fn (QuizKind $kind) => [$kind->value => $kind->label()])->all(),
            'types' => collect(QuestionType::cases())->mapWithKeys(fn (QuestionType $type) => [$type->value => $type->label()])->all(),
            'questions' => $quiz->questions->map(fn (QuizQuestion $question) => [
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'options' => $question->options ?? [],
                // Short answers are edited as one line: "Abuja | FCT".
                'answer' => $question->type === QuestionType::ShortAnswer ? [implode(' | ', $question->answer)] : $question->answer,
                'marks' => $question->marks,
            ])->values(),
        ]);
    }

    public function updateQuiz(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeSubject($request->user(), $quiz->classSubject);

        $data = $request->validateWithBag('settings', [
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::enum(QuizKind::class)],
            'duration_minutes' => ['nullable', 'integer', 'between:1,300'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'shuffle_questions' => ['boolean'],
            'show_answers' => ['boolean'],
        ]);

        $quiz->update($data);

        return back()->with('status', 'Settings saved.');
    }

    /**
     * Save every question at once (JSON, from the builder). Locked once a student has started.
     */
    public function saveQuestions(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeSubject($request->user(), $quiz->classSubject);

        if ($quiz->attempts()->exists()) {
            throw ValidationException::withMessages(['questions' => 'Students have started this, so its questions can no longer change.']);
        }

        $data = $request->validate([
            'questions' => ['present', 'array', 'max:200'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.prompt' => ['required', 'string', 'max:2000'],
            'questions.*.marks' => ['required', 'integer', 'between:1,20'],
            'questions.*.options' => ['nullable', 'array', 'max:8'],
            'questions.*.options.*.key' => ['required', 'string', 'max:5'],
            'questions.*.options.*.text' => ['required', 'string', 'max:500'],
            'questions.*.answer' => ['required', 'array', 'min:1'],
            'questions.*.answer.*' => ['required', 'string', 'max:200'],
        ], [
            'questions.*.prompt.required' => 'Every question needs some text.',
            'questions.*.options.*.text.required' => 'Fill in every option, or remove the empty ones.',
            'questions.*.answer.required' => 'Mark the correct answer for every question.',
        ]);

        foreach ($data['questions'] as $index => $question) {
            $type = QuestionType::from($question['type']);
            $keys = array_column($question['options'] ?? [], 'key');

            if (in_array($type, [QuestionType::SingleChoice, QuestionType::MultipleChoice], true) && (count($keys) < 2 || array_diff($question['answer'], $keys) !== [])) {
                throw ValidationException::withMessages(["questions.{$index}.answer" => 'Question '.($index + 1).' needs at least two options and a correct answer chosen from them.']);
            }
        }

        DB::transaction(function () use ($quiz, $data): void {
            $quiz->questions()->delete();

            foreach (array_values($data['questions']) as $position => $question) {
                $type = QuestionType::from($question['type']);
                $quiz->questions()->create([
                    'type' => $type,
                    'prompt' => $question['prompt'],
                    'options' => $type === QuestionType::TrueFalse ? [['key' => 'true', 'text' => 'True'], ['key' => 'false', 'text' => 'False']] : ($type === QuestionType::ShortAnswer ? null : array_values($question['options'] ?? [])),
                    'answer' => array_values($question['answer']),
                    'marks' => $question['marks'],
                    'position' => $position + 1,
                ]);
            }
        });

        return response()->json(['saved' => count($data['questions']), 'total_marks' => (int) array_sum(array_column($data['questions'], 'marks'))]);
    }

    public function togglePublish(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeSubject($request->user(), $quiz->classSubject);

        if ($quiz->status === ClassworkStatus::Draft && ! $quiz->questions()->exists()) {
            return back()->with('status', 'Add at least one question before publishing.');
        }

        $quiz->update(['status' => $quiz->status === ClassworkStatus::Draft ? ClassworkStatus::Published : ClassworkStatus::Draft]);

        return back()->with('status', $quiz->status === ClassworkStatus::Published ? "{$quiz->title} is now open to the class." : "{$quiz->title} is hidden from students again.");
    }

    public function quizResults(Request $request, Quiz $quiz): View
    {
        $this->authorizeSubject($request->user(), $quiz->classSubject);
        $quiz->load(['classSubject.subject', 'classSubject.classArm.level', 'term']);

        $students = Enrolment::query()->with('student')
            ->where('class_arm_id', $quiz->classSubject->class_arm_id)
            ->where('academic_session_id', $quiz->term->academic_session_id)
            ->get()
            ->map->student
            ->sortBy(fn ($student) => $student->last_name.' '.$student->first_name)
            ->values();

        return view('school.classwork.quiz-results', [
            'quiz' => $quiz,
            'students' => $students,
            'attempts' => $quiz->attempts()->get()->keyBy('student_id'),
            'components' => AssessmentComponent::query()->orderBy('position')->get(),
        ]);
    }

    public function sendToScorebook(Request $request, Quiz $quiz, SendQuizScoresToScorebook $send): RedirectResponse
    {
        $this->authorizeSubject($request->user(), $quiz->classSubject);
        $data = $request->validate(['component_id' => ['required', TenantRule::exists('assessment_components')]]);
        $component = AssessmentComponent::query()->findOrFail($data['component_id']);

        $count = $send->handle($quiz->load(['term', 'classSubject']), $component, $request->user());

        return back()->with('status', "{$count} ".str('score')->plural($count)." sent to {$component->short_name} in the scorebook.");
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('assignment', [
            'class_subject_id' => ['required', TenantRule::exists('class_subjects')],
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['required', 'string', 'max:5000'],
            'due_at' => ['required', 'date', 'after:now'],
            'max_score' => ['nullable', 'integer', 'between:1,100'],
            'publish' => ['boolean'],
            ...StoredFiles::rules('files'),
        ]);

        $classSubject = ClassSubject::query()->findOrFail($data['class_subject_id']);
        $this->authorizeSubject($request->user(), $classSubject);

        $assignment = Assignment::query()->create([
            ...Arr::except($data, ['publish', 'files']),
            'files' => $request->hasFile('files') ? StoredFiles::store($request->file('files'), 'schools/'.$classSubject->school_id.'/assignments') : null,
            'term_id' => $this->termId(),
            'created_by' => $request->user()->id,
            'status' => ($data['publish'] ?? true) ? ClassworkStatus::Published : ClassworkStatus::Draft,
        ]);

        return to_route('school.classwork.assignments.show', $assignment)->with('status', "{$assignment->title} set for {$classSubject->classArm->displayName()}.");
    }

    public function showAssignment(Request $request, Assignment $assignment): View
    {
        $this->authorizeSubject($request->user(), $assignment->classSubject);
        $assignment->load(['classSubject.subject', 'classSubject.classArm.level', 'term']);

        $students = Enrolment::query()->with('student')
            ->where('class_arm_id', $assignment->classSubject->class_arm_id)
            ->where('academic_session_id', $assignment->term->academic_session_id)
            ->get()->map->student
            ->sortBy(fn ($student) => $student->last_name.' '.$student->first_name)
            ->values();

        return view('school.classwork.assignment', [
            'assignment' => $assignment,
            'students' => $students,
            'submissions' => $assignment->submissions()->get()->keyBy('student_id'),
        ]);
    }

    /**
     * One student's handed-in work, opened beside the marking form.
     */
    public function showSubmission(Request $request, AssignmentSubmission $submission): View
    {
        $assignment = $submission->assignment;
        $this->authorizeSubject($request->user(), $assignment->classSubject);
        $assignment->load(['classSubject.subject', 'classSubject.classArm.level']);

        $all = $assignment->submissions()->with('student')->get()
            ->sortBy(fn (AssignmentSubmission $other) => $other->student->last_name.' '.$other->student->first_name)
            ->values();
        $position = $all->search(fn (AssignmentSubmission $other) => $other->is($submission));

        return view('school.classwork.submission', [
            'assignment' => $assignment,
            'submission' => $submission->load(['student', 'marker']),
            'previous' => $position > 0 ? $all[$position - 1] : null,
            'next' => $all[$position + 1] ?? null,
            'position' => $position + 1,
            'count' => $all->count(),
            'unmarked' => $all->whereNull('marked_at')->count(),
        ]);
    }

    public function markSubmission(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $assignment = $submission->assignment;
        $this->authorizeSubject($request->user(), $assignment->classSubject);

        $data = $request->validateWithBag('mark'.$submission->id, [
            'score' => ['nullable', 'numeric', 'min:0', 'max:'.($assignment->max_score ?? 100)],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission->update([...$data, 'marked_by' => $request->user()->id, 'marked_at' => now()]);

        if ($request->boolean('next')) {
            $next = $assignment->submissions()->with('student')->whereNull('marked_at')->get()
                ->sortBy(fn (AssignmentSubmission $other) => $other->student->last_name.' '.$other->student->first_name)
                ->first();

            return $next
                ? to_route('school.classwork.submissions.show', $next)->with('status', "Marked {$submission->student->first_name}’s work. Here’s the next one.")
                : to_route('school.classwork.assignments.show', $assignment)->with('status', 'All handed-in work is marked.');
        }

        return back()->with('status', "Marked {$submission->student->first_name}’s work.");
    }

    private function authorizeSubject(User $user, ClassSubject $classSubject): void
    {
        abort_unless(ClassworkAccess::canManage($user, $classSubject), 403);
    }

    private function termId(): int
    {
        return Term::current()->id ?? throw ValidationException::withMessages(['class_subject_id' => 'Make a term current before setting classwork.']);
    }
}
