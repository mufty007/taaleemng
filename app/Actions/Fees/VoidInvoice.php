<?php

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancel an invoice issued by mistake. Anything already paid on it becomes
 * credit and moves to the student's other unpaid invoices.
 */
class VoidInvoice
{
    public function __construct(private AllocatePayments $allocate) {}

    public function handle(Invoice $invoice, User $by, string $reason): void
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw ValidationException::withMessages(['reason' => 'This invoice is already cancelled.']);
        }

        DB::transaction(function () use ($invoice, $reason): void {
            $invoice->allocations()->delete();
            $invoice->update([
                'status' => InvoiceStatus::Void,
                'amount_paid' => 0,
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').'Cancelled: '.$reason),
            ]);
        });

        $this->allocate->handle($invoice->student);

        activity('fees')->performedOn($invoice)->causedBy($by)->log("cancelled invoice {$invoice->invoice_no}: {$reason}");
    }
}
