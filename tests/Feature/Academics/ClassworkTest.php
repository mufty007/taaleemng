<?php

use App\Enums\ClassworkStatus;
use App\Enums\QuestionType;
use App\Models\AssessmentComponent;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassArm;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Score;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->teacher = staffOf($this->school);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha'])->student;
    $this->outsider = enrol($this->school, 'JSS 2', 'A')->student;

    inSchool($this->school, function () {
        $arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->maths = $arm->classSubjects()->where('subject_id', Subject::query()->where('code', 'MTH')->value('id'))->sole();
        $this->maths->update(['teacher_id' => $this->teacher->id]);
        $this->english = $arm->classSubjects()->where('subject_id', Subject::query()->where('code', 'ENG')->value('id'))->sole();
    });

    $this->makeQuiz = function (array $overrides = []) {
        $this->actingAs($this->teacher->user)->post(route('school.classwork.quizzes.store', $this->school), [
            'class_subject_id' => $this->maths->id, 'title' => 'Fractions quiz', 'kind' => 'quiz', ...$overrides,
        ])->assertRedirect();

        return inSchool($this->school, fn () => Quiz::query()->latest('id')->firstOrFail());
    };
    $this->questions = [
        ['type' => 'single', 'prompt' => '1/2 + 1/4 = ?', 'marks' => 2, 'options' => [['key' => 'A', 'text' => '3/4'], ['key' => 'B', 'text' => '2/6']], 'answer' => ['A']],
        ['type' => 'multiple', 'prompt' => 'Which equal one half?', 'marks' => 2, 'options' => [['key' => 'A', 'text' => '2/4'], ['key' => 'B', 'text' => '3/6'], ['key' => 'C', 'text' => '2/3']], 'answer' => ['A', 'B']],
        ['type' => 'true_false', 'prompt' => '0.5 is a half', 'marks' => 1, 'answer' => ['true']],
        ['type' => 'short', 'prompt' => 'Capital of Nigeria?', 'marks' => 1, 'answer' => ['Abuja', 'FCT']],
    ];
});

it('lets a teacher build and publish a quiz for their own subject only', function () {
    $quiz = ($this->makeQuiz)();

    $this->actingAs($this->teacher->user)->putJson(route('school.classwork.quizzes.questions', [$this->school, $quiz]), ['questions' => $this->questions])
        ->assertOk()->assertJson(['saved' => 4, 'total_marks' => 6]);
    $this->actingAs($this->teacher->user)->post(route('school.classwork.quizzes.publish', [$this->school, $quiz]));

    expect($quiz->fresh()->status)->toBe(ClassworkStatus::Published);

    $this->actingAs($this->teacher->user)->post(route('school.classwork.quizzes.store', $this->school), ['class_subject_id' => $this->english->id, 'title' => 'Not mine', 'kind' => 'quiz'])->assertForbidden();
    $this->actingAs(staffOf($this->school)->user)->get(route('school.classwork.quizzes.edit', [$this->school, $quiz]))->assertForbidden();
});

it('refuses a multiple choice question whose answer is not one of its options', function () {
    $quiz = ($this->makeQuiz)();

    $this->actingAs($this->teacher->user)->putJson(route('school.classwork.quizzes.questions', [$this->school, $quiz]), ['questions' => [
        ['type' => 'single', 'prompt' => 'Pick one', 'marks' => 1, 'options' => [['key' => 'A', 'text' => 'Yes'], ['key' => 'B', 'text' => 'No']], 'answer' => ['C']],
    ]])->assertUnprocessable()->assertJsonValidationErrors('questions.0.answer');
});

it('lets the class take the quiz once and marks it straight away', function () {
    $quiz = ($this->makeQuiz)();
    $this->actingAs($this->teacher->user)->putJson(route('school.classwork.quizzes.questions', [$this->school, $quiz]), ['questions' => $this->questions]);
    $this->actingAs($this->teacher->user)->post(route('school.classwork.quizzes.publish', [$this->school, $quiz]));
    $ids = inSchool($this->school, fn () => $quiz->questions()->pluck('id')->all());
    $student = $this->aisha->user;

    $this->actingAs($student)->get(route('school.portal.classwork', $this->school))->assertOk()->assertSee('Fractions quiz');
    $this->actingAs($student)->post(route('school.portal.quizzes.start', [$this->school, $quiz]))->assertRedirect();
    $this->actingAs($student)->putJson(route('school.portal.quizzes.answer', [$this->school, $quiz]), ['answers' => [$ids[0] => 'A', $ids[1] => ['B', 'A'], $ids[2] => 'false']])->assertOk();
    $this->actingAs($student)->post(route('school.portal.quizzes.submit', [$this->school, $quiz]), ['answers' => [$ids[0] => 'A', $ids[1] => ['B', 'A'], $ids[2] => 'false', $ids[3] => '  abuja ']])->assertRedirect();

    $attempt = inSchool($this->school, fn () => QuizAttempt::query()->sole());
    expect($attempt->score)->toBe(5.0)->and($attempt->max_score)->toBe(6)->and($attempt->percent())->toBe(83);

    $this->actingAs($student)->post(route('school.portal.quizzes.submit', [$this->school, $quiz]))->assertStatus(409);
    $this->actingAs($student)->get(route('school.portal.quizzes.show', [$this->school, $quiz]))->assertSee('5 / 6');
    $this->actingAs($this->outsider->user)->get(route('school.portal.quizzes.show', [$this->school, $quiz]))->assertNotFound();
    $this->actingAs($this->teacher->user)->putJson(route('school.classwork.quizzes.questions', [$this->school, $quiz]), ['questions' => $this->questions])->assertUnprocessable();
});

it('hides drafts from students', function () {
    $quiz = ($this->makeQuiz)();

    $this->actingAs($this->aisha->user)->get(route('school.portal.quizzes.show', [$this->school, $quiz]))->assertNotFound();
});

it('sends quiz percentages to the scorebook as a CA', function () {
    $quiz = ($this->makeQuiz)();
    inSchool($this->school, function () use ($quiz) {
        $quiz->update(['status' => ClassworkStatus::Published]);
        QuizAttempt::query()->create(['quiz_id' => $quiz->id, 'student_id' => $this->aisha->id, 'started_at' => now(), 'submitted_at' => now(), 'score' => 15, 'max_score' => 20]);
    });
    $ca1 = inSchool($this->school, fn () => AssessmentComponent::query()->where('short_name', 'CA1')->sole());

    $this->actingAs($this->teacher->user)->post(route('school.classwork.quizzes.scorebook', [$this->school, $quiz]), ['component_id' => $ca1->id])
        ->assertSessionHas('status', '1 score sent to CA1 in the scorebook.');

    inSchool($this->school, fn () => expect(Score::query()->sole()->score)->toBe(15.0));
});

it('sets an assignment, takes a hand-in and marks it', function () {
    $this->actingAs($this->teacher->user)->post(route('school.classwork.assignments.store', $this->school), [
        'class_subject_id' => $this->maths->id, 'title' => 'Exercise 4.2', 'instructions' => 'Do questions 1 to 10.', 'due_at' => now()->addWeek()->format('Y-m-d H:i'), 'max_score' => 10, 'publish' => 1,
    ])->assertRedirect();
    $assignment = inSchool($this->school, fn () => Assignment::query()->sole());

    $this->actingAs($this->aisha->user)->post(route('school.portal.assignments.submit', [$this->school, $assignment]), ['body' => 'My working…'])->assertRedirect();
    $submission = inSchool($this->school, fn () => AssignmentSubmission::query()->sole());

    $this->actingAs($this->teacher->user)->get(route('school.classwork.assignments.show', [$this->school, $assignment]))->assertOk()->assertSee('To mark');
    $this->actingAs($this->teacher->user)->get(route('school.classwork.submissions.show', [$this->school, $submission]))->assertOk()->assertSee('My working');
    $this->actingAs($this->teacher->user)->patch(route('school.classwork.submissions.mark', [$this->school, $submission]), ['score' => 8, 'feedback' => 'Good'])->assertSessionHasNoErrors();
    $this->actingAs($this->teacher->user)->patch(route('school.classwork.submissions.mark', [$this->school, $submission]), ['score' => 11])->assertSessionHasErrorsIn('mark'.$submission->id, 'score');

    $this->actingAs($this->aisha->user)->get(route('school.portal.assignments.show', [$this->school, $assignment]))->assertSee('8 / 10')->assertSee('Good');
    $this->actingAs($this->aisha->user)->post(route('school.portal.assignments.submit', [$this->school, $assignment]), ['body' => 'Changed'])
        ->assertSessionHas('status', 'Your teacher has marked this already, so it can’t be changed.');
});

it('takes an uploaded paper and shows it only to the teacher, the student and their parents', function () {
    Storage::fake('local');

    $this->actingAs($this->teacher->user)->post(route('school.classwork.assignments.store', $this->school), [
        'class_subject_id' => $this->maths->id, 'title' => 'Essay', 'instructions' => 'Write 300 words.', 'due_at' => now()->addWeek()->format('Y-m-d H:i'), 'publish' => 1,
        'files' => [UploadedFile::fake()->create('worksheet.pdf', 200, 'application/pdf')],
    ])->assertSessionHasNoErrors();
    $assignment = inSchool($this->school, fn () => Assignment::query()->sole());
    expect($assignment->files)->toHaveCount(1);

    $this->actingAs($this->aisha->user)->post(route('school.portal.assignments.submit', [$this->school, $assignment]), [])
        ->assertSessionHasErrors('body');
    $this->actingAs($this->aisha->user)->post(route('school.portal.assignments.submit', [$this->school, $assignment]), [
        'files' => [UploadedFile::fake()->image('page-1.png'), UploadedFile::fake()->create('essay.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
    ])->assertSessionHasNoErrors();
    $submission = inSchool($this->school, fn () => AssignmentSubmission::query()->sole());
    expect($submission->files)->toHaveCount(2)->and($submission->body)->toBeNull();
    Storage::disk('local')->assertExists($submission->files[0]['path']);

    $file = fn (int $index) => route('school.classwork.submissions.file', [$this->school, $submission, $index]);
    $this->actingAs($this->teacher->user)->get($file(0))->assertOk()->assertHeader('content-disposition');
    $this->actingAs($this->aisha->user)->get($file(1))->assertOk();
    $this->actingAs($this->outsider->user)->get($file(0))->assertNotFound();
    $this->actingAs(staffOf($this->school)->user)->get($file(0))->assertNotFound();
    $this->actingAs($this->aisha->user)->get(route('school.classwork.assignments.file', [$this->school, $assignment, 0]))->assertOk();
    $this->actingAs($this->outsider->user)->get(route('school.classwork.assignments.file', [$this->school, $assignment, 0]))->assertNotFound();

    // Handing in again can drop a file and add another.
    $this->actingAs($this->aisha->user)->post(route('school.portal.assignments.submit', [$this->school, $assignment]), [
        'remove_files' => [1], 'files' => [UploadedFile::fake()->image('page-2.png')],
    ])->assertSessionHasNoErrors();
    expect(collect($submission->fresh()->files)->pluck('name')->all())->toBe(['page-1.png', 'page-2.png']);
    Storage::disk('local')->assertMissing($submission->files[1]['path']);

    $this->actingAs($this->teacher->user)->get(route('school.classwork.submissions.show', [$this->school, $submission]))->assertOk()->assertSee('page-2.png');
});

it('moves on to the next unmarked hand-in after saving a mark', function () {
    $second = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Zainab', 'last_name' => 'Zubairu'])->student;
    [$assignment, $first, $next] = inSchool($this->school, function () use ($second) {
        $assignment = Assignment::query()->create([
            'class_subject_id' => $this->maths->id, 'term_id' => Term::current()->id,
            'title' => 'Exercise', 'instructions' => 'Go', 'due_at' => now()->addDay(), 'status' => ClassworkStatus::Published,
        ]);

        return [
            $assignment,
            AssignmentSubmission::query()->create(['assignment_id' => $assignment->id, 'student_id' => $this->aisha->id, 'body' => 'A', 'submitted_at' => now()]),
            AssignmentSubmission::query()->create(['assignment_id' => $assignment->id, 'student_id' => $second->id, 'body' => 'B', 'submitted_at' => now()]),
        ];
    });

    $this->actingAs($this->teacher->user)->patch(route('school.classwork.submissions.mark', [$this->school, $first]), ['score' => 5, 'next' => 1])
        ->assertRedirect(route('school.classwork.submissions.show', [$this->school, $next]));
    $this->actingAs($this->teacher->user)->patch(route('school.classwork.submissions.mark', [$this->school, $next]), ['score' => 7, 'next' => 1])
        ->assertRedirect(route('school.classwork.assignments.show', [$this->school, $assignment]));
});

it('marks each question type', function (QuestionType $type, array $answer, mixed $response, bool $correct) {
    expect($type->isCorrect($answer, $response))->toBe($correct);
})->with([
    [QuestionType::SingleChoice, ['B'], 'B', true],
    [QuestionType::SingleChoice, ['B'], 'A', false],
    [QuestionType::MultipleChoice, ['A', 'C'], ['C', 'A'], true],
    [QuestionType::MultipleChoice, ['A', 'C'], ['A'], false],
    [QuestionType::TrueFalse, ['false'], 'false', true],
    [QuestionType::ShortAnswer, ['Abuja', 'FCT'], ' fct ', true],
    [QuestionType::ShortAnswer, ['Abuja'], '', false],
    [QuestionType::ShortAnswer, ['Abuja'], null, false],
]);
