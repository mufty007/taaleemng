<?php

namespace App\Enums;

/**
 * What happens to a student at the end of the session, decided on the third-term report.
 */
enum PromotionDecision: string
{
    case Promoted = 'promoted';
    case Probation = 'probation';
    case Repeat = 'repeat';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Promoted => 'Promoted',
            self::Probation => 'Promoted on probation',
            self::Repeat => 'Advised to repeat',
            self::Graduated => 'Graduated',
        };
    }

    /**
     * Whether the student moves up a class next session.
     */
    public function movesUp(): bool
    {
        return $this === self::Promoted || $this === self::Probation;
    }
}
