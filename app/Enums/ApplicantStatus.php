<?php

namespace App\Enums;

/**
 * Where an application is in the admissions pipeline.
 */
enum ApplicantStatus: string
{
    case Submitted = 'submitted';
    case Screening = 'screening';
    case ExamScheduled = 'exam_scheduled';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Enrolled = 'enrolled';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'New',
            self::Screening => 'Screening',
            self::ExamScheduled => 'Exam booked',
            self::Offered => 'Offered a place',
            self::Accepted => 'Offer accepted',
            self::Enrolled => 'Enrolled',
            self::Rejected => 'Not admitted',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'brand',
            self::Screening, self::ExamScheduled => 'gold',
            self::Offered, self::Accepted => 'sky',
            self::Enrolled => 'naija',
            self::Rejected => 'danger',
            self::Withdrawn => 'neutral',
        };
    }

    /**
     * The tab an application is listed under on the admissions page.
     */
    public function stage(): string
    {
        return match ($this) {
            self::Submitted, self::Screening => 'new',
            self::ExamScheduled => 'exam',
            self::Offered, self::Accepted => 'offered',
            self::Enrolled => 'enrolled',
            self::Rejected, self::Withdrawn => 'closed',
        };
    }

    /**
     * @return list<self>
     */
    public static function inStage(string $stage): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->stage() === $stage));
    }

    /**
     * Statuses staff can move an application to from this one (enrolling has its own step).
     *
     * @return list<self>
     */
    public function nextSteps(): array
    {
        return match ($this) {
            self::Submitted => [self::Screening, self::ExamScheduled, self::Offered, self::Rejected, self::Withdrawn],
            self::Screening => [self::ExamScheduled, self::Offered, self::Rejected, self::Withdrawn],
            self::ExamScheduled => [self::Offered, self::Rejected, self::Withdrawn],
            self::Offered => [self::Accepted, self::Rejected, self::Withdrawn],
            self::Accepted => [self::Withdrawn],
            self::Rejected, self::Withdrawn => [self::Screening],
            self::Enrolled => [],
        };
    }

    public function canEnrol(): bool
    {
        return $this === self::Offered || $this === self::Accepted;
    }
}
