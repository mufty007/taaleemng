<?php

namespace App\Http\Controllers\School;

use App\Actions\Payroll\PreparePayroll;
use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Support\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Monthly staff payroll: prepare a run from salaries on file, mark paid, print payslips.
 */
class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $year = $request->integer('year', (int) now()->year);

        return view('school.payroll.index', [
            'year' => $year,
            'years' => PayrollRun::query()->select('year')->distinct()->orderByDesc('year')->pluck('year')
                ->merge([(int) now()->year, (int) now()->year - 1])->unique()->sortDesc()->values(),
            'runs' => PayrollRun::query()->with('preparer')
                ->where('year', $year)
                ->orderByDesc('month')
                ->get(),
            'withSalary' => Staff::query()->withSalary()->count(),
            'withoutSalary' => Staff::query()->where(fn ($query) => $query->whereNull('basic_salary')->orWhere('basic_salary', '<=', 0))->count(),
            'monthOptions' => collect(range(1, 12))->mapWithKeys(
                fn (int $month) => [$month => Carbon::createFromDate($year, $month, 1)->format('F')]
            )->all(),
            'canManage' => $request->user()->can('payroll.manage'),
        ]);
    }

    public function store(Request $request, PreparePayroll $preparePayroll): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        ['run' => $run, 'skipped' => $skipped] = $preparePayroll->handle(
            (int) $data['year'],
            (int) $data['month'],
            $request->user(),
        );

        $status = 'Payroll for '.$run->label().' is ready: '.$run->headcount.' staff, '.Money::format($run->net_total).' net.';
        if ($skipped > 0) {
            $status .= " {$skipped} active staff were skipped (no basic salary).";
        }

        return redirect()->route('school.payroll.show', $run)->with('status', $status);
    }

    public function show(Request $request, PayrollRun $payrollRun): View
    {
        $payrollRun->load(['lines' => fn ($query) => $query->orderBy('staff_name'), 'preparer']);

        return view('school.payroll.show', [
            'run' => $payrollRun,
            'canManage' => $request->user()->can('payroll.manage'),
        ]);
    }

    public function markPaid(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        abort_if($payrollRun->isVoid(), 409);
        abort_if($payrollRun->isPaid(), 409);

        $payrollRun->lines()->whereNull('paid_at')->update(['paid_at' => now()]);
        $payrollRun->update([
            'status' => PayrollRunStatus::Paid,
            'paid_at' => now(),
        ]);

        activity('finance')->performedOn($payrollRun)->log('marked '.$payrollRun->label().' payroll as paid ('.Money::format($payrollRun->net_total).')');

        return back()->with('status', $payrollRun->label().' payroll is marked as paid.');
    }

    public function markLinePaid(Request $request, PayrollRun $payrollRun, PayrollLine $payrollLine): RedirectResponse
    {
        abort_unless($payrollLine->payroll_run_id === $payrollRun->id, 404);
        abort_if($payrollRun->isVoid(), 409);
        abort_if($payrollLine->isPaid(), 409);

        $payrollLine->update(['paid_at' => now()]);

        if ($payrollRun->lines()->whereNull('paid_at')->doesntExist()) {
            $payrollRun->update(['status' => PayrollRunStatus::Paid, 'paid_at' => now()]);
        }

        activity('finance')->performedOn($payrollLine)->log('marked '.$payrollLine->staff_name.'’s '.$payrollRun->label().' payslip as paid');

        return back()->with('status', $payrollLine->staff_name.'’s payslip is marked as paid.');
    }

    public function void(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        abort_if($payrollRun->isVoid(), 409);

        $data = $request->validateWithBag('voidPayroll', [
            'reason' => ['required', 'string', 'max:255'],
        ], ['reason.required' => 'Say why this payroll is being voided.']);

        $payrollRun->update([
            'status' => PayrollRunStatus::Voided,
            'voided_at' => now(),
            'void_reason' => $data['reason'],
        ]);

        activity('finance')->performedOn($payrollRun)->log('voided '.$payrollRun->label().' payroll: '.$data['reason']);

        return redirect()->route('school.payroll.index')->with('status', $payrollRun->label().' payroll was voided. You can prepare that month again.');
    }

    public function payslip(Request $request, CurrentSchool $currentSchool, PayrollRun $payrollRun, PayrollLine $payrollLine): View
    {
        abort_unless($payrollLine->payroll_run_id === $payrollRun->id, 404);

        return view('school.payroll.payslip', [
            'school' => $currentSchool->getOrFail(),
            'run' => $payrollRun,
            'line' => $payrollLine,
        ]);
    }

    public function sheet(Request $request, CurrentSchool $currentSchool, PayrollRun $payrollRun): View
    {
        $payrollRun->load(['lines' => fn ($query) => $query->orderBy('staff_name')]);

        return view('school.payroll.sheet', [
            'school' => $currentSchool->getOrFail(),
            'run' => $payrollRun,
        ]);
    }
}
