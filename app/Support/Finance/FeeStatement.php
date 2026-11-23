<?php

namespace App\Support\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Support\Money;
use App\Support\Payments\Paystack;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;

/**
 * What a student owes, the link a parent can pay it from without signing in,
 * and the reminder message that carries that link.
 */
final class FeeStatement
{
    /** How long a pay link in a reminder keeps working. */
    public const LINK_DAYS = 30;

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function __construct(
        public readonly Student $student,
        public readonly Collection $invoices,
    ) {}

    public static function for(Student $student): self
    {
        return new self($student, Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
            ->with(['term.academicSession', 'lines'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Invoice $invoice): string => $invoice->term->starts_on->toDateString())
            ->values());
    }

    public function owed(): int
    {
        return (int) $this->invoices->sum(fn (Invoice $invoice): int => $invoice->balance());
    }

    /**
     * A signed link to this student's fees page: works for whoever the school sends it to, for 30 days.
     */
    public function payLink(): string
    {
        return self::linkFor($this->student);
    }

    public static function linkFor(Student $student): string
    {
        return URL::temporarySignedRoute('pay.show', now()->addDays(self::LINK_DAYS), [
            'school' => app(CurrentSchool::class)->getOrFail()->slug,
            'student' => $student->ulid,
        ]);
    }

    /**
     * Whether parents can pay this school online (platform Paystack plus the school's payout account).
     */
    public static function canPayOnline(): bool
    {
        $paystack = app(Paystack::class);

        if (! (app(CurrentSchool::class)->get()?->hasModule('online-payments') ?? true)) {
            return false;
        }

        return $paystack->isConfigured()
            && (filled(SchoolSetting::valueFor('paystack_subaccount')) || ! $paystack->requiresSubaccount());
    }

    /**
     * The words of a reminder, for email or WhatsApp.
     */
    public function message(School $school, ?Guardian $guardian, string $link): string
    {
        return self::messageFor($school, $this->student, $guardian, $this->owed(), $link);
    }

    public static function messageFor(School $school, Student $student, ?Guardian $guardian, int $owed, string $link, ?bool $online = null): string
    {
        $greeting = $guardian ? 'Dear '.trim(($guardian->title ? $guardian->title.' ' : '').$guardian->last_name).',' : 'Dear Parent,';
        $action = ($online ?? self::canPayOnline()) ? 'Pay securely online here' : 'See the details here';

        return "{$greeting} this is a reminder from {$school->name} that {$student->fullName()}’s school fees of "
            .Money::format($owed)." are outstanding. {$action}: {$link} Thank you.";
    }
}
