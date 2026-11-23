<?php

namespace App\Actions\Billing;

use App\Enums\SchoolInvoiceStatus;
use App\Models\SchoolInvoice;
use App\Models\User;
use App\Support\Payments\Paystack;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Send a school admin to Paystack to pay a Ta'aleem subscription invoice.
 * Settles to the platform account (no school subaccount).
 */
class StartSchoolSubscriptionPayment
{
    public function __construct(private Paystack $paystack) {}

    public function handle(SchoolInvoice $invoice, User $payer): string
    {
        if (! $this->paystack->isConfigured()) {
            throw ValidationException::withMessages(['payment' => 'Online payment is not available right now.']);
        }

        abort_unless($invoice->status === SchoolInvoiceStatus::Sent, 422, 'Only a sent invoice can be paid online.');
        abort_unless($invoice->amount > 0, 422, 'This invoice has nothing to pay.');

        $reference = $invoice->paystack_reference ?: 'tal-sub-'.$invoice->ulid.'-'.Str::lower(Str::random(8));
        $email = $payer->email ?: $invoice->school->email ?: 'billing@taaleem.ng';

        $checkout = $this->paystack->initialize(
            $email,
            $invoice->amount,
            $reference,
            route('school.settings.subscription.callback', $invoice->school),
            [
                'type' => 'school_subscription',
                'school_id' => $invoice->school_id,
                'school_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
            ],
        );

        $invoice->update(['paystack_reference' => $reference]);

        return $checkout['authorization_url'];
    }
}
