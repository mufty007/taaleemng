<?php

namespace App\Actions\Billing;

use App\Enums\SchoolInvoiceStatus;
use App\Enums\SchoolStatus;
use App\Models\SchoolInvoice;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mark a Ta'aleem subscription invoice paid after Paystack verifies the charge.
 */
class ConfirmSchoolSubscriptionPayment
{
    /**
     * @param  array<string, mixed>  $transaction
     */
    public function handle(string $reference, array $transaction): ?SchoolInvoice
    {
        return DB::transaction(function () use ($reference, $transaction): ?SchoolInvoice {
            $invoice = SchoolInvoice::query()
                ->where('paystack_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status === SchoolInvoiceStatus::Paid) {
                return $invoice;
            }

            $succeeded = ($transaction['status'] ?? null) === 'success'
                && (int) ($transaction['amount'] ?? 0) === $invoice->amount
                && strtoupper((string) ($transaction['currency'] ?? 'NGN')) === 'NGN';

            if (! $succeeded) {
                return $invoice;
            }

            $paidAt = isset($transaction['paid_at'])
                ? Carbon::parse((string) $transaction['paid_at'])->setTimezone(config('app.timezone'))
                : now();

            $invoice->update([
                'status' => SchoolInvoiceStatus::Paid,
                'paid_at' => $paidAt,
                'payment_method' => 'Paystack',
                'payment_reference' => $reference,
            ]);

            $school = $invoice->school;
            $paidUntil = $school->billing_cycle === 'annual'
                ? $paidAt->copy()->addYear()
                : $paidAt->copy()->addMonths(4);

            $school->update([
                'paid_until' => $paidUntil,
                'status' => $school->status === SchoolStatus::Trial ? SchoolStatus::Active : $school->status,
            ]);

            activity('platform')
                ->performedOn($invoice)
                ->tap(fn ($activity) => $activity->school_id = $school->id)
                ->log('received '.Money::format($invoice->amount)." for subscription invoice {$invoice->number}");

            return $invoice;
        });
    }
}
