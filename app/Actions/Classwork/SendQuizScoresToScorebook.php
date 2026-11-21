<?php

namespace App\Actions\Classwork;

use App\Models\AssessmentComponent;
use App\Models\Enrolment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Use a quiz or CBT exam as a continuous assessment: each student's percentage
 * becomes their score for the chosen component (e.g. 15/20 → CA1 = 15 of 20).
 */
class SendQuizScoresToScorebook
{
    public function handle(Quiz $quiz, AssessmentComponent $component, User $by): int
    {
        if ($quiz->term->isClosed()) {
            throw ValidationException::withMessages(['component' => "{$quiz->term->name} is closed, so the scorebook can’t be changed."]);
        }

        $classSubject = $quiz->classSubject;
        $enrolments = Enrolment::query()
            ->where('class_arm_id', $classSubject->class_arm_id)
            ->where('academic_session_id', $quiz->term->academic_session_id)
            ->pluck('id', 'student_id');

        $attempts = $quiz->attempts()->whereNotNull('submitted_at')->get()->filter(fn (QuizAttempt $attempt) => $enrolments->has($attempt->student_id) && $attempt->max_score > 0);

        DB::transaction(function () use ($attempts, $enrolments, $quiz, $classSubject, $component, $by): void {
            foreach ($attempts as $attempt) {
                Score::query()->updateOrCreate(
                    ['term_id' => $quiz->term_id, 'enrolment_id' => $enrolments[$attempt->student_id], 'class_subject_id' => $classSubject->id, 'assessment_component_id' => $component->id],
                    ['score' => round((float) $attempt->score / $attempt->max_score * $component->max_score, 1), 'entered_by' => $by->id],
                );
            }
        });

        activity('results')->performedOn($quiz)->log("sent {$attempts->count()} {$quiz->kind->label()} scores to {$component->short_name}");

        return $attempts->count();
    }
}
