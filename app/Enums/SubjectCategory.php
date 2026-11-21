<?php

namespace App\Enums;

enum SubjectCategory: string
{
    case Core = 'core';
    case Elective = 'elective';
    case Language = 'language';
    case Religious = 'religious';
    case Vocational = 'vocational';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core',
            self::Elective => 'Elective',
            self::Language => 'Language',
            self::Religious => 'Religious studies',
            self::Vocational => 'Vocational',
        };
    }
}
