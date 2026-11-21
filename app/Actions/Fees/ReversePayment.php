<?php

namespace App\Actions\Fees;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Undo a payment recorded in error (a bounced cheque, a duplicate entry). The
 * receipt stays on record, marked reversed, and the invoices it paid reopen.
 */
class ReversePayment
{
    public function __construct(private AllocatePayments $allocate) {}

    public function handle(Payment $payment, User $by, string $reason): void
    {
        if ($payment->status !== PaymentStatus::Confirmed) {
            throw ValidationException::withMessages(['reason' => 'Only a confirmed payment can be reversed.']);
        }

        DB::transaction(function () use ($payment, $by, $reason): void {
            $invoiceIds = $payment->allocations()->pluck('invoice_id');
            $payment->allocations()->delete();
            $payment->update([
                'status' => PaymentStatus::Reversed,
                'meta' => [...($payment->meta ?? []), 'reversed_by' => $by->id, 'reversed_at' => now()->toIso8601String(), 'reversal_reason' => $reason],
            ]);

            Invoice::query()->whereKey($invoiceIds)->get()->each(fn (Invoice $invoice) => $invoice->refreshPaid());
        });

        $this->allocate->handle($payment->student);

        activity('fees')->performedOn($payment)->causedBy($by)
            ->log('reversed receipt '.$payment->receipt_no.' ('.Money::format($payment->amount)."): {$reason}");
    }
}
