<?php

namespace App\Support\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Term;
use App\Support\Finance\CashflowPeriod;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Income and expenditure for a term, the way a proprietor or board reads it: fees in,
 * spending out by category, the largest items, the month-by-month statement, payroll
 * runs for the period, and how the term compares with the ones before it.
 *
 * Spending is the expense book. Payroll runs are listed for reference only, because
 * schools record the salary payment itself as a "Salaries & allowances" expense.
 */
final class FinanceReport implements SchoolReport
{
    private CashflowPeriod $period;

    public function __construct(public readonly Term $term)
    {
        $this->period = CashflowPeriod::forTerm($term);
    }

    public function periodLabel(): string
    {
        return $this->term->name.' · '.$this->term->academicSession->name;
    }

    public function figures(): array
    {
        $income = $this->period->income();
        $spending = $this->period->spending();
        $billed = (int) Invoice::query()->where('term_id', $this->term->id)->where('status', '!=', InvoiceStatus::Void)->sum('total');

        return [
            'term' => $this->term,
            'period' => $this->period,
            'stats' => [
                'income' => $income,
                'spending' => $spending,
                'net' => $income - $spending,
                'margin' => $income > 0 ? (int) round(($income - $spending) / $income * 100) : null,
                'billed' => $billed,
                'expense_count' => $this->expenses()->count(),
            ],
            'months' => $this->period->months(),
            'categories' => $this->period->byCategory(),
            'methods' => $this->period->incomeByMethod(),
            'largest' => $this->expenses()->orderByDesc('amount')->limit(10)->get(),
            'payroll' => $this->payrollRuns(),
            'comparison' => $this->comparison(),
        ];
    }

    public function csv(): StreamedResponse
    {
        $payments = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$this->period->from->copy()->startOfDay(), $this->period->to->copy()->endOfDay()])
            ->with('student')
            ->get()
            ->map(fn (Payment $payment) => [
                $payment->paid_at?->format('Y-m-d'),
                'Income',
                'School fees',
                trim(($payment->student?->registerName() ?? 'Student').' · '.($payment->receipt_no ?? '')),
                $payment->method->label(),
                Money::toNaira($payment->amount),
            ]);

        $expenses = $this->expenses()->get()->map(fn (Expense $expense) => [
            $expense->spent_on->format('Y-m-d'),
            'Expense',
            $expense->category->label(),
            $expense->description.($expense->paid_to ? ' · '.$expense->paid_to : ''),
            $expense->method->label(),
            '-'.Money::toNaira($expense->amount),
        ]);

        return CsvDownload::make(
            'income-expenditure-'.str($this->term->name.' '.$this->term->academicSession->name)->slug().'.csv',
            ['Date', 'Type', 'Category', 'Details', 'Method', 'Amount (₦)'],
            $payments->concat($expenses)->sortBy(fn (array $row) => $row[0])->values(),
        );
    }

    /**
     * @return Builder<Expense>
     */
    private function expenses(): Builder
    {
        return Expense::query()->counted()->whereBetween('spent_on', [$this->period->from->toDateString(), $this->period->to->toDateString()]);
    }

    /**
     * Payroll runs for the months the period covers.
     *
     * @return Collection<int, PayrollRun>
     */
    private function payrollRuns(): Collection
    {
        $months = [];

        for ($month = $this->period->from->copy()->startOfMonth(); $month->lte($this->period->to); $month->addMonth()) {
            $months[] = $month->year * 100 + $month->month;
        }

        return PayrollRun::query()->counted()
            ->whereRaw('(year * 100 + month) between ? and ?', [min($months), max($months)])
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    /**
     * This term against up to five terms before it.
     *
     * @return list<array{label: string, income: int, spending: int, net: int, current: bool}>
     */
    private function comparison(): array
    {
        return Term::query()
            ->with('academicSession')
            ->where('starts_on', '<=', $this->term->starts_on)
            ->orderByDesc('starts_on')
            ->limit(6)
            ->get()
            ->reverse()
            ->map(function (Term $term): array {
                $period = $term->is($this->term) ? $this->period : CashflowPeriod::forTerm($term);
                $income = $period->income();
                $spending = $period->spending();

                return [
                    'label' => $term->name.' '.$term->academicSession->name,
                    'income' => $income,
                    'spending' => $spending,
                    'net' => $income - $spending,
                    'current' => $term->is($this->term),
                ];
            })
            ->values()
            ->all();
    }
}
