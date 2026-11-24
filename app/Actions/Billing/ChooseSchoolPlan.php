<?php

namespace App\Actions\Billing;

use App\Enums\BillingModel;
use App\Enums\Plan;
use App\Enums\SchoolInvoiceStatus;
use App\Models\School;
use App\Models\SchoolInvoice;
use App\Models\Term;
use App\Models\User;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\SeatLimits;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * A school picks a plan and how it pays: the invoice to take to Paystack.
 * The school moves to the plan only once the payment is confirmed.
 */
class ChooseSchoolPlan
{
    public function __construct(private SeatLimits $seats, private IssueSchoolInvoice $issueInvoice) {}

    public function handle(School $school, Plan $plan, BillingModel $model, string $cycle, User $chooser): SchoolInvoice
    {
        $students = $this->seats->studentCount($school);
        $staff = $this->seats->staffCount($school);

        if ($plan->maxStudents() !== null && $students > $plan->maxStudents()) {
            throw ValidationException::withMessages(['plan' => "You have {$students} students and the {$plan->label()} plan allows ".number_format($plan->maxStudents()).'. Choose a bigger plan.']);
        }

        if ($plan->maxStaff() !== null && $staff > $plan->maxStaff()) {
            throw ValidationException::withMessages(['plan' => "You have {$staff} staff and the {$plan->label()} plan allows ".number_format($plan->maxStaff()).'. Choose a bigger plan.']);
        }

        $amount = PlanCatalog::invoiceAmount($plan, $model, $cycle, $students);

        if (! $amount) {
            throw ValidationException::withMessages(['plan' => $model === BillingModel::PerStudent && $students === 0
                ? 'Per-student billing starts once you have students. Choose the flat fee for now.'
                : 'This plan has no price set yet. Contact Ta’aleem to subscribe.']);
        }

        $open = SchoolInvoice::query()
            ->where('school_id', $school->id)
            ->where('status', SchoolInvoiceStatus::Sent)
            ->whereNotNull('plan');

        // Clicking "Pay" twice reuses the invoice; choosing something else replaces it.
        $same = (clone $open)
            ->where('plan', $plan->value)
            ->where('billing_model', $model->value)
            ->where('billing_cycle', $cycle)
            ->where('amount', $amount)
            ->first();

        if ($same) {
            return $same;
        }

        $open->update(['status' => SchoolInvoiceStatus::Void]);

        return $this->issueInvoice->handle([
            'school_id' => $school->id,
            'period' => $this->period($school, $plan, $cycle),
            'plan' => $plan,
            'billing_model' => $model->value,
            'billing_cycle' => $cycle,
            'students' => $students,
            'unit_price' => $model === BillingModel::PerStudent ? intdiv($amount, max(1, $students)) : $amount,
            'amount' => $amount,
            'status' => SchoolInvoiceStatus::Sent,
            'due_on' => $this->dueOn($school),
            'notes' => 'Chosen by '.$chooser->name.' in Settings.',
            'created_by' => $chooser->id,
        ]);
    }

    private function period(School $school, Plan $plan, string $cycle): string
    {
        $term = Term::query()->withoutGlobalScopes()->where('school_id', $school->id)
            ->whereDate('ends_on', '>=', today())->orderBy('starts_on')
            ->with(['academicSession' => fn ($query) => $query->withoutGlobalScopes()])
            ->first();

        $span = match (true) {
            $term === null => $cycle === 'annual' ? 'one year' : 'one term',
            $cycle === 'annual' => $term->academicSession->name.' session',
            default => $term->name.' '.$term->academicSession->name,
        };

        return "{$plan->label()} plan · {$span}";
    }

    /**
     * Due when the trial or the paid-up period runs out, and never sooner than a week.
     */
    private function dueOn(School $school): Carbon
    {
        $coveredUntil = collect([$school->trial_ends_at, $school->paid_until])->filter()->max();
        $weekAway = today()->addWeek();

        return $coveredUntil && $coveredUntil->gt($weekAway) ? Carbon::parse($coveredUntil)->startOfDay() : $weekAway;
    }
}
