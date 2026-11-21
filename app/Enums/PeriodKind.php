<?php

namespace App\Enums;

enum PeriodKind: string
{
    case Lesson = 'lesson';
    case Break = 'break';
    case Assembly = 'assembly';

    public function label(): string
    {
        return match ($this) {
            self::Lesson => 'Lesson',
            self::Break => 'Break',
            self::Assembly => 'Assembly',
        };
    }

    public function isLesson(): bool
    {
        return $this === self::Lesson;
    }
}
