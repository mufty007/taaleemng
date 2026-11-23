<?php

namespace App\Actions\Fees;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use App\Support\Numbering\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Settle a pending online payment with what Paystack's verify API says.
 *
 * Idempotent: the webhook and the parent's return to the site can both arrive,
 * and Paystack retries webhooks, so a payment is only ever confirmed once.
 */
class ConfirmOnlinePayment
{
    public function __construct(private SequenceGenerator $sequences, private AllocatePayments $allocate) {}

    /**
     * @param  array<string, mixed>  $transaction  the `data` of Paystack's verify response
     */
    public function handle(string $reference, array $transaction): ?Payment
    {
        $confirmedNow = false;

        $payment = DB::transaction(function () use ($reference, $transaction, &$confirmedNow): ?Payment {
            $payment = Payment::query()
                ->where('method', PaymentMethod::Paystack)
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            // A failed attempt can still turn into a payment: on Paystack's checkout the parent can
            // try another card under the same reference. Only a confirmed payment is settled for good.
            if ($payment === null || $payment->status === PaymentStatus::Confirmed) {
                return $payment;
            }

            $gateway = [
                'gateway_status' => $transaction['status'] ?? null,
                'gateway_response' => $transaction['gateway_response'] ?? null,
                'channel' => $transaction['channel'] ?? null,
                'paid_amount' => $transaction['amount'] ?? null,
            ];

            $succeeded = ($transaction['status'] ?? null) === 'success'
                && (int) ($transaction['amount'] ?? 0) === $payment->amount
                && strtoupper((string) ($transaction['currency'] ?? 'NGN')) === 'NGN';

            if (! $succeeded) {
                // "abandoned" is how Paystack reports a checkout that was opened but not paid yet: the parent
                // may still finish it, so it stays pending. Failures, reversals and wrong amounts are recorded.
                if (in_array($transaction['status'] ?? null, ['failed', 'reversed'], true) || ($transaction['status'] ?? null) === 'success') {
                    $payment->update(['status' => PaymentStatus::Failed, 'meta' => [...($payment->meta ?? []), ...$gateway]]);
                }

                return $payment;
            }

            $paidAt = isset($transaction['paid_at']) ? Carbon::parse((string) $transaction['paid_at'])->setTimezone(config('app.timezone')) : now();

            $payment->update([
                'status' => PaymentStatus::Confirmed,
                'receipt_no' => $this->sequences->next('receipt_no', $paidAt),
                'paid_at' => $paidAt,
                'meta' => [...($payment->meta ?? []), ...$gateway],
            ]);
            $confirmedNow = true;

            return $payment;
        });

        if ($payment !== null && $confirmedNow) {
            $this->allocate->handle($payment->student);
            activity('fees')->performedOn($payment)->log('received '.Money::format($payment->amount)." online for {$payment->student->fullName()}, receipt {$payment->receipt_no}");
        }

        return $payment;
    }
}
