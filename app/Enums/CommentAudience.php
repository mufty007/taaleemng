<?php

namespace App\Enums;

/**
 * Who writes a report-card comment.
 */
enum CommentAudience: string
{
    case FormTeacher = 'form_teacher';
    case Principal = 'principal';

    public function label(): string
    {
        return match ($this) {
            self::FormTeacher => 'Form teacher',
            self::Principal => 'Principal / Head teacher',
        };
    }
}
