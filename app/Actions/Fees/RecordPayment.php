<?php

namespace App\Actions\Fees;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use App\Support\Numbering\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The bursar records money received at the school (cash, transfer, POS, cheque).
 * It gets a receipt number and pays off the oldest invoices first.
 */
class RecordPayment
{
    public function __construct(private SequenceGenerator $sequences, private AllocatePayments $allocate) {}

    public function handle(Student $student, int $amount, PaymentMethod $method, Carbon $paidAt, User $recordedBy, ?string $reference = null, ?string $notes = null): Payment
    {
        $payment = DB::transaction(fn (): Payment => Payment::query()->create([
            'receipt_no' => $this->sequences->next('receipt_no', $paidAt),
            'student_id' => $student->id,
            'guardian_id' => $student->guardians()->wherePivot('is_primary', true)->value('guardians.id'),
            'amount' => $amount,
            'method' => $method,
            'reference' => filled($reference) ? $reference : null,
            'status' => PaymentStatus::Confirmed,
            'paid_at' => $paidAt,
            'recorded_by' => $recordedBy->id,
            'notes' => $notes,
        ]));

        $this->allocate->handle($student);

        activity('fees')->performedOn($payment)->causedBy($recordedBy)
            ->log('recorded '.Money::format($amount)." ({$method->label()}) for {$student->fullName()}, receipt {$payment->receipt_no}");

        return $payment;
    }
}
