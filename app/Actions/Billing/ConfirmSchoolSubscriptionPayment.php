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
            $cycle = $invoice->billing_cycle ?? $school->billing_cycle;

            // Paying early adds to the time already paid for, instead of losing it.
            $from = $school->paid_until?->isAfter($paidAt) ? $school->paid_until->copy() : $paidAt->copy();
            $paidUntil = $cycle === 'annual' ? $from->addYear() : $from->addMonths(4);

            $changes = [
                'paid_until' => $paidUntil,
                'status' => $school->status === SchoolStatus::Trial ? SchoolStatus::Active : $school->status,
            ];

            // The school chose this plan itself: switch to it now that it is paid for.
            if ($invoice->plan !== null) {
                $changes += [
                    'plan' => $invoice->plan,
                    'billing_model' => $invoice->billing_model ?? $school->billing_model,
                    'billing_cycle' => $cycle,
                    // A hand-set seat cap only survives if it is more generous than the new plan.
                    'max_students' => $school->max_students !== null && $school->max_students > ($invoice->plan->maxStudents() ?? PHP_INT_MAX) ? $school->max_students : null,
                    'max_staff' => $school->max_staff !== null && $school->max_staff > ($invoice->plan->maxStaff() ?? PHP_INT_MAX) ? $school->max_staff : null,
                ];
            }

            $school->update($changes);

            activity('platform')
                ->performedOn($invoice)
                ->tap(fn ($activity) => $activity->school_id = $school->id)
                ->log('received '.Money::format($invoice->amount)." for subscription invoice {$invoice->number}");

            return $invoice;
        });
    }
}
