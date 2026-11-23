<?php

namespace App\Actions\Fees;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Support\Finance\FeeStatement;
use App\Support\Payments\Paystack;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A parent pays an invoice online: record a pending payment and get the
 * Paystack checkout page to send them to. Nothing counts until Paystack confirms.
 * Settlements use the school's Paystack subaccount under the platform account.
 */
class StartOnlinePayment
{
    public function __construct(private Paystack $paystack, private CurrentSchool $currentSchool) {}

    public function handle(Invoice $invoice, User $payer): string
    {
        $amount = $invoice->balance();

        if ($amount <= 0) {
            throw ValidationException::withMessages(['invoice' => 'This invoice is already paid.']);
        }

        return $this->start(
            student: $invoice->student,
            amount: $amount,
            email: $payer->email ?? preg_replace('/\D/', '', (string) $payer->phone).'@parents.taaleem.ng',
            guardianId: $payer->guardianRecord()?->id,
            callbackUrl: route('school.portal.payments.callback'),
            meta: ['invoice_no' => $invoice->invoice_no, 'payer_user_id' => $payer->id],
        );
    }

    /**
     * Settle everything a student owes in one payment (from a pay link, or a parent's
     * "Pay now"); confirmed money is then spread over the invoices, oldest first.
     */
    public function forStudent(Student $student, string $email, ?Guardian $guardian, ?string $callbackUrl = null, ?User $payer = null): string
    {
        $statement = FeeStatement::for($student);

        if ($statement->owed() <= 0) {
            throw ValidationException::withMessages(['email' => 'These fees are already paid. Thank you.']);
        }

        return $this->start(
            student: $student,
            amount: $statement->owed(),
            email: $email,
            guardianId: $guardian?->id,
            callbackUrl: $callbackUrl ?? route('pay.callback'),
            meta: array_filter([
                'invoice_no' => $statement->invoices->pluck('invoice_no')->join(', '),
                'via' => $payer ? 'portal' : 'pay-link',
                'payer_email' => $email,
                'payer_user_id' => $payer?->id,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function start(Student $student, int $amount, string $email, ?int $guardianId, string $callbackUrl, array $meta): string
    {
        if (! $this->paystack->isConfigured()) {
            throw ValidationException::withMessages(['invoice' => 'Online payments are not available right now. Pay at the bursary instead.']);
        }

        $subaccount = SchoolSetting::valueFor('paystack_subaccount');

        if (blank($subaccount) && $this->paystack->requiresSubaccount()) {
            throw ValidationException::withMessages(['invoice' => 'This school has not finished setting up online payouts yet. Pay at the bursary, or ask the school office.']);
        }

        $school = $this->currentSchool->getOrFail();

        $payment = Payment::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardianId,
            'amount' => $amount,
            'method' => PaymentMethod::Paystack,
            'reference' => self::reference($school->id),
            'status' => PaymentStatus::Pending,
            'meta' => $meta,
        ]);

        $checkout = $this->paystack->initialize(
            email: $email,
            amount: $amount,
            reference: $payment->reference,
            callbackUrl: $callbackUrl,
            metadata: [
                'school' => $school->name,
                'student' => $student->fullName(),
                'invoice_no' => $meta['invoice_no'] ?? null,
            ],
            subaccount: filled($subaccount) ? (string) $subaccount : null,
        );

        return $checkout['authorization_url'];
    }

    /**
     * References carry the school's id, so a webhook can find the right school
     * before anything else is looked up.
     */
    public static function reference(int $schoolId): string
    {
        return 'TAL'.$schoolId.'-'.Str::lower((string) Str::ulid());
    }

    public static function schoolIdFromReference(string $reference): ?int
    {
        return preg_match('/^TAL(\d+)-[0-9a-z]{26}$/', $reference, $matches) ? (int) $matches[1] : null;
    }
}
