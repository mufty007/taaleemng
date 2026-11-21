<?php

namespace App\Enums;

enum QuizKind: string
{
    case Quiz = 'quiz';
    case Test = 'test';
    case Exam = 'exam';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'Quiz',
            self::Test => 'Class test',
            self::Exam => 'Exam (CBT)',
        };
    }
}
