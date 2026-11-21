<?php

namespace App\Actions\Classwork;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;

/**
 * Hand in a quiz attempt and mark it. Every question type marks itself, so
 * the student sees their score straight away.
 */
class SubmitQuizAttempt
{
    public function handle(QuizAttempt $attempt, Quiz $quiz): QuizAttempt
    {
        if ($attempt->submitted_at !== null) {
            return $attempt;
        }

        $questions = $quiz->questions()->get();
        $answers = $attempt->answers ?? [];

        $attempt->update([
            'submitted_at' => now(),
            'score' => $questions->sum(fn (QuizQuestion $question) => $question->type->isCorrect($question->answer, $answers[$question->id] ?? null) ? $question->marks : 0),
            'max_score' => $questions->sum('marks'),
        ]);

        return $attempt;
    }
}
