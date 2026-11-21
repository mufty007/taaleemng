<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Classwork\SubmitQuizAttempt;
use App\Enums\ClassworkStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassSubject;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Support\Classwork\ClassworkAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A student's classwork: assignments to hand in, and quizzes and CBT exams to take.
 */
class ClassworkController extends Controller
{
    public function index(Request $request): View
    {
        $student = $this->student($request);
        $subjectIds = ClassSubject::query()->where('class_arm_id', ClassworkAccess::classArmIdOf($student) ?? 0)->pluck('id');

        return view('portal.classwork.index', [
            'student' => $student,
            'assignments' => Assignment::query()->with('classSubject.subject')
                ->whereIn('class_subject_id', $subjectIds)->where('status', ClassworkStatus::Published)
                ->orderBy('due_at')->get(),
            'submissions' => AssignmentSubmission::query()->where('student_id', $student->id)->get()->keyBy('assignment_id'),
            'quizzes' => Quiz::query()->with('classSubject.subject')->withSum('questions', 'marks')
                ->whereIn('class_subject_id', $subjectIds)->where('status', ClassworkStatus::Published)
                ->latest()->get(),
            'attempts' => QuizAttempt::query()->where('student_id', $student->id)->get()->keyBy('quiz_id'),
        ]);
    }

    public function assignment(Request $request, Assignment $assignment): View
    {
        $student = $this->student($request);
        $this->ensureFor($student, $assignment->classSubject, $assignment->status);

        return view('portal.classwork.assignment', [
            'assignment' => $assignment->load('classSubject.subject', 'classSubject.teacher'),
            'submission' => $assignment->submissions()->where('student_id', $student->id)->first(),
        ]);
    }

    public function submitAssignment(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $this->student($request);
        $this->ensureFor($student, $assignment->classSubject, $assignment->status);
        $submission = $assignment->submissions()->where('student_id', $student->id)->first();

        if ($submission?->marked_at) {
            return back()->with('status', 'Your teacher has marked this already, so it can’t be changed.');
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:20000']], ['body.required' => 'Write your answer before handing it in.']);

        AssignmentSubmission::query()->updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            ['body' => $data['body'], 'submitted_at' => now()],
        );

        return to_route('school.portal.classwork')->with('status', 'Handed in'.($assignment->isPastDue() ? ' (late)' : '').'. Well done.');
    }

    public function quiz(Request $request, Quiz $quiz): View
    {
        $student = $this->student($request);
        $this->ensureFor($student, $quiz->classSubject, $quiz->status);
        $attempt = $quiz->attempts()->where('student_id', $student->id)->first();
        $quiz->load(['classSubject.subject', 'questions']);

        if ($attempt && $attempt->submitted_at === null && $this->timeIsUp($attempt, $quiz)) {
            $attempt = app(SubmitQuizAttempt::class)->handle($attempt, $quiz);
        }

        return view('portal.classwork.quiz', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'deadline' => $attempt?->deadline($quiz),
            'showAnswers' => $attempt?->submitted_at && $quiz->show_answers && ($quiz->closes_at === null || $quiz->hasClosed()),
        ]);
    }

    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $student = $this->student($request);
        $this->ensureFor($student, $quiz->classSubject, $quiz->status);
        abort_unless($quiz->isOpen(), 403);

        QuizAttempt::query()->firstOrCreate(
            ['quiz_id' => $quiz->id, 'student_id' => $student->id],
            ['started_at' => now(), 'answers' => [], 'max_score' => (int) $quiz->questions()->sum('marks')],
        );

        return to_route('school.portal.quizzes.show', $quiz);
    }

    /**
     * Autosave answers while the student works (JSON).
     */
    public function answer(Request $request, Quiz $quiz): JsonResponse
    {
        $attempt = $this->openAttempt($request, $quiz);
        $data = $request->validate(['answers' => ['present', 'array'], 'answers.*' => ['nullable']]);

        $attempt->update(['answers' => array_filter($data['answers'], fn ($value) => $value !== null && $value !== '' && $value !== [])]);

        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, Quiz $quiz, SubmitQuizAttempt $submit): RedirectResponse
    {
        $attempt = $this->openAttempt($request, $quiz, allowLate: true);

        if ($request->has('answers') && ! $this->timeIsUp($attempt, $quiz)) {
            $attempt->update(['answers' => array_filter((array) $request->input('answers', []), fn ($value) => $value !== null && $value !== '' && $value !== [])]);
        }

        $submit->handle($attempt, $quiz);

        return to_route('school.portal.quizzes.show', $quiz)->with('status', 'Submitted. Your score is below.');
    }

    private function openAttempt(Request $request, Quiz $quiz, bool $allowLate = false): QuizAttempt
    {
        $student = $this->student($request);
        $this->ensureFor($student, $quiz->classSubject, $quiz->status);
        $attempt = $quiz->attempts()->where('student_id', $student->id)->firstOrFail();

        abort_if($attempt->submitted_at !== null, 409, 'This has already been submitted.');
        abort_if(! $allowLate && $this->timeIsUp($attempt, $quiz), 409, 'Time is up.');

        return $attempt;
    }

    private function timeIsUp(QuizAttempt $attempt, Quiz $quiz): bool
    {
        $deadline = $attempt->deadline($quiz);

        // A minute's grace for the last autosave on a slow connection.
        return $deadline !== null && now()->gt($deadline->copy()->addMinute());
    }

    private function student(Request $request): Student
    {
        return $request->user()->studentRecord() ?? abort(403);
    }

    private function ensureFor(Student $student, ClassSubject $classSubject, ClassworkStatus $status): void
    {
        abort_unless($status === ClassworkStatus::Published && ClassworkAccess::isFor($student, $classSubject), 404);
    }
}
