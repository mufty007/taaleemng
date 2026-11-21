<?php

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Applies a student's confirmed money to their unpaid invoices, oldest first.
 * Anything left over stays on the payment as credit, and is used up the next
 * time an invoice is issued.
 */
class AllocatePayments
{
    public function handle(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            $payments = Payment::query()
                ->where('student_id', $student->id)
                ->where('status', PaymentStatus::Confirmed)
                ->withSum('allocations', 'amount')
                ->orderBy('paid_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (Payment $payment) => $payment->amount > (int) $payment->allocations_sum_amount);

            if ($payments->isEmpty()) {
                return;
            }

            $invoices = Invoice::query()
                ->where('student_id', $student->id)
                ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
                ->orderBy('issued_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $available = $payment->amount - (int) $payment->allocations_sum_amount;

                foreach ($invoices as $invoice) {
                    $owed = $invoice->total - $invoice->amount_paid;

                    if ($available <= 0 || $owed <= 0) {
                        continue;
                    }

                    $amount = min($available, $owed);
                    $allocation = PaymentAllocation::query()->firstOrNew(['payment_id' => $payment->id, 'invoice_id' => $invoice->id]);
                    $allocation->amount = ($allocation->amount ?? 0) + $amount;
                    $allocation->save();

                    $available -= $amount;
                    $invoice->amount_paid += $amount;
                }
            }

            $invoices->each(fn (Invoice $invoice) => $invoice->refreshPaid());
        });
    }

    /**
     * After an invoice's total changes (a discount, an extra charge): give back
     * anything paid beyond the new total as credit, then apply credit again.
     */
    public function rebalance(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $excess = (int) $invoice->allocations()->sum('amount') - max(0, $invoice->total);

            foreach ($invoice->allocations()->orderByDesc('id')->get() as $allocation) {
                if ($excess <= 0) {
                    break;
                }

                $take = min($excess, $allocation->amount);
                $excess -= $take;
                $allocation->amount -= $take;
                $allocation->amount === 0 ? $allocation->delete() : $allocation->save();
            }

            $invoice->refreshPaid();
        });

        $this->handle($invoice->student);
    }

    /**
     * What the student owes across all open invoices, less any unused credit (kobo).
     */
    public static function balance(Student $student): int
    {
        $owed = (int) Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
            ->sum(DB::raw('total - amount_paid'));

        $credit = (int) Payment::query()
            ->where('student_id', $student->id)
            ->where('status', PaymentStatus::Confirmed)
            ->withSum('allocations', 'amount')
            ->get()
            ->sum(fn (Payment $payment) => max(0, $payment->amount - (int) $payment->allocations_sum_amount));

        return $owed - $credit;
    }
}
