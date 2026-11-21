<?php

namespace App\Actions\Payroll;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\User;
use App\Support\Finance\CashflowPeriod;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Build a month’s payroll from active staff who have a basic salary set.
 */
class PreparePayroll
{
    /**
     * @return array{run: PayrollRun, skipped: int}
     */
    public function handle(int $year, int $month, User $preparer): array
    {
        abort_unless($month >= 1 && $month <= 12, 422);
        abort_unless($year >= 2000 && $year <= 2100, 422);

        $existing = PayrollRun::query()
            ->counted()
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'month' => $existing->label().' payroll is already prepared. Open it, or void it first to start again.',
            ]);
        }

        $staff = Staff::query()
            ->active()
            ->withSalary()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($staff->isEmpty()) {
            throw ValidationException::withMessages([
                'month' => 'No active staff have a basic salary yet. Set salaries on each staff record first.',
            ]);
        }

        $periodDay = Carbon::createFromDate($year, $month, 15)->startOfDay();
        $skipped = Staff::query()->active()->where(fn ($query) => $query->whereNull('basic_salary')->orWhere('basic_salary', '<=', 0))->count();

        return DB::transaction(function () use ($year, $month, $preparer, $staff, $periodDay, $skipped): array {
            $run = PayrollRun::query()->create([
                'year' => $year,
                'month' => $month,
                'term_id' => CashflowPeriod::termFor($periodDay)?->id,
                'status' => PayrollRunStatus::Prepared,
                'prepared_by' => $preparer->id,
            ]);

            $gross = 0;
            $deductions = 0;
            $net = 0;

            foreach ($staff as $member) {
                $basic = (int) $member->basic_salary;
                $allowances = (int) $member->allowances;
                $lineDeductions = (int) $member->deductions;
                $lineNet = max(0, $basic + $allowances - $lineDeductions);

                PayrollLine::query()->create([
                    'payroll_run_id' => $run->id,
                    'staff_id' => $member->id,
                    'staff_name' => $member->fullName(),
                    'staff_no' => $member->staff_no,
                    'designation' => $member->designation,
                    'basic_salary' => $basic,
                    'allowances' => $allowances,
                    'deductions' => $lineDeductions,
                    'net_pay' => $lineNet,
                    'bank_name' => $member->bank_name,
                    'bank_account_name' => $member->bank_account_name,
                    'bank_account_number' => $member->bank_account_number,
                ]);

                $gross += $basic + $allowances;
                $deductions += $lineDeductions;
                $net += $lineNet;
            }

            $run->update([
                'gross_total' => $gross,
                'deductions_total' => $deductions,
                'net_total' => $net,
                'headcount' => $staff->count(),
            ]);

            activity('finance')->performedOn($run)->log(
                'prepared payroll for '.$run->label().': '.$staff->count().' staff, '.Money::format($net).' net'
            );

            return ['run' => $run->refresh(), 'skipped' => $skipped];
        });
    }
}
