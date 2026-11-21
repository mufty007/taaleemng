<?php

namespace App\Enums;

enum QuestionType: string
{
    case SingleChoice = 'single';
    case MultipleChoice = 'multiple';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Multiple choice (one answer)',
            self::MultipleChoice => 'Multiple choice (several answers)',
            self::TrueFalse => 'True or false',
            self::ShortAnswer => 'Short answer',
        };
    }

    /**
     * Whether a response earns the marks. Choices are option keys; short
     * answers match any accepted answer, ignoring case and extra spaces.
     *
     * @param  list<string>  $answer  the correct option keys, or accepted short answers
     */
    public function isCorrect(array $answer, mixed $response): bool
    {
        $normalise = fn (mixed $value): string => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $value)));

        return match ($this) {
            self::SingleChoice, self::TrueFalse => is_string($response) && in_array($response, $answer, true),
            self::MultipleChoice => is_array($response) && collect($response)->map(fn ($key) => (string) $key)->sort()->values()->all() === collect($answer)->sort()->values()->all(),
            self::ShortAnswer => is_string($response) && $normalise($response) !== '' && in_array($normalise($response), array_map($normalise, $answer), true),
        };
    }
}
