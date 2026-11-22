<?php

namespace App\Actions\Fees;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\User;
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

        if (! $this->paystack->isConfigured()) {
            throw ValidationException::withMessages(['invoice' => 'Online payments are not available right now. Pay at the bursary instead.']);
        }

        $subaccount = SchoolSetting::valueFor('paystack_subaccount');

        if (blank($subaccount)) {
            throw ValidationException::withMessages(['invoice' => 'This school has not finished setting up online payouts yet. Pay at the bursary, or ask the school office.']);
        }

        $school = $this->currentSchool->getOrFail();

        $payment = Payment::query()->create([
            'student_id' => $invoice->student_id,
            'guardian_id' => $payer->guardianRecord()?->id,
            'amount' => $amount,
            'method' => PaymentMethod::Paystack,
            'reference' => self::reference($school->id),
            'status' => PaymentStatus::Pending,
            'meta' => ['invoice_no' => $invoice->invoice_no, 'payer_user_id' => $payer->id],
        ]);

        $checkout = $this->paystack->initialize(
            email: $payer->email ?? preg_replace('/\D/', '', (string) $payer->phone).'@parents.taaleem.ng',
            amount: $amount,
            reference: $payment->reference,
            callbackUrl: route('school.portal.payments.callback'),
            metadata: [
                'school' => $school->name,
                'student' => $invoice->student->fullName(),
                'invoice_no' => $invoice->invoice_no,
            ],
            subaccount: (string) $subaccount,
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
