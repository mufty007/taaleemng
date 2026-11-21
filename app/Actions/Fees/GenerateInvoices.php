<?php

namespace App\Actions\Fees;

use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Term;
use App\Support\Numbering\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bills every enrolled student for a term from the fee schedule. Students who
 * already have an invoice for the term are skipped, so it can be run again after
 * new students join. Any credit a family already has is applied straight away.
 */
class GenerateInvoices
{
    public function __construct(private SequenceGenerator $sequences, private AllocatePayments $allocate) {}

    /**
     * @param  list<int>|null  $classLevelIds  only these classes, or every class
     * @return array{created: int, existing: int, unpriced: int}
     */
    public function handle(Term $term, ?array $classLevelIds = null, ?Carbon $dueOn = null): array
    {
        $levelOfArm = ClassArm::query()->pluck('class_level_id', 'id');
        $schedule = FeeStructure::query()
            ->where('term_id', $term->id)
            ->whereHas('feeItem', fn ($query) => $query->where('is_optional', false))
            ->with('feeItem')
            ->get()
            ->sortBy(fn (FeeStructure $structure) => sprintf('%05d-%s', $structure->feeItem->position, $structure->feeItem->name))
            ->groupBy('class_level_id');
        $alreadyBilled = Invoice::query()->where('term_id', $term->id)->pluck('enrolment_id')->flip();

        $enrolments = Enrolment::query()
            ->with('student')
            ->where('academic_session_id', $term->academic_session_id)
            // Everyone who was in the class that session, including those since promoted or graduated.
            ->where('status', '!=', EnrolmentStatus::Withdrawn)
            ->whereHas('student', fn ($query) => $query->whereNotIn('status', [StudentStatus::Withdrawn, StudentStatus::Transferred]))
            ->get()
            ->filter(fn (Enrolment $enrolment) => $classLevelIds === null || in_array($levelOfArm[$enrolment->class_arm_id] ?? null, $classLevelIds, true));

        $counts = ['created' => 0, 'existing' => 0, 'unpriced' => 0];

        foreach ($enrolments as $enrolment) {
            if ($alreadyBilled->has($enrolment->id)) {
                $counts['existing']++;

                continue;
            }

            $lines = $schedule->get($levelOfArm[$enrolment->class_arm_id] ?? 0, collect())->filter(fn (FeeStructure $structure) => $structure->amount > 0);

            if ($lines->isEmpty()) {
                $counts['unpriced']++;

                continue;
            }

            DB::transaction(function () use ($enrolment, $term, $lines, $dueOn): void {
                $invoice = Invoice::query()->create([
                    'invoice_no' => $this->sequences->next('invoice_no'),
                    'student_id' => $enrolment->student_id,
                    'enrolment_id' => $enrolment->id,
                    'term_id' => $term->id,
                    'total' => $lines->sum('amount'),
                    'status' => InvoiceStatus::Unpaid,
                    'due_on' => $dueOn ?? $term->starts_on->copy()->addWeeks(2),
                    'issued_at' => now(),
                ]);

                $invoice->lines()->createMany($lines->map(fn (FeeStructure $structure) => [
                    'fee_item_id' => $structure->fee_item_id,
                    'description' => $structure->feeItem->name,
                    'amount' => $structure->amount,
                ])->values()->all());
            });

            $this->allocate->handle($enrolment->student);
            $counts['created']++;
        }

        activity('fees')->withProperties($counts)->log("issued {$counts['created']} invoices for {$term->name}");

        return $counts;
    }
}
