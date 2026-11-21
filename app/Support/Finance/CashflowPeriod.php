<?php

namespace App\Support\Finance;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A stretch of the school year for the books: a term, counted from the day after the
 * previous term closed (so fees paid in the holiday land in the term they are for)
 * to the day the term closes. Without a term, the last 90 days.
 */
final class CashflowPeriod
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?Term $term,
    ) {}

    public static function forTerm(?Term $term): self
    {
        if ($term === null) {
            return new self(today()->subDays(90), today(), null);
        }

        $previous = Term::query()->where('ends_on', '<', $term->starts_on)->orderByDesc('ends_on')->first();

        return new self(
            $previous ? $previous->ends_on->copy()->addDay() : $term->starts_on->copy()->subWeeks(3),
            $term->ends_on->copy(),
            $term->loadMissing('academicSession'),
        );
    }

    /**
     * The term a day's spending belongs to.
     */
    public static function termFor(Carbon $date): ?Term
    {
        return Term::query()->orderBy('starts_on')->get()
            ->first(fn (Term $term) => $date->betweenIncluded(self::forTerm($term)->from, $term->ends_on));
    }

    public function label(): string
    {
        return $this->term ? $this->term->name.' '.$this->term->academicSession->name : 'Last 90 days';
    }

    /**
     * Fees in: confirmed payments in the period.
     */
    public function income(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    /**
     * Money out: expenses in the period, leaving out voided ones.
     */
    public function spending(): int
    {
        return (int) $this->expenses()->sum('amount');
    }

    /**
     * Month by month: in, out and the difference, up to today.
     *
     * @return Collection<int, array{month: string, in: int, out: int, net: int}>
     */
    public function months(): Collection
    {
        $payments = $this->payments()->get(['amount', 'paid_at']);
        $expenses = $this->expenses()->get(['amount', 'spent_on']);
        $months = collect();

        for ($month = $this->from->copy()->startOfMonth(); $month->lte(min($this->to, today())); $month->addMonth()) {
            $in = (int) $payments->filter(fn (Payment $payment) => $payment->paid_at?->isSameMonth($month))->sum('amount');
            $out = (int) $expenses->filter(fn (Expense $expense) => $expense->spent_on->isSameMonth($month))->sum('amount');
            $months->push(['month' => $month->format('M Y'), 'in' => $in, 'out' => $out, 'net' => $in - $out]);
        }

        return $months;
    }

    /**
     * Spending by category, largest first.
     *
     * @return Collection<int, array{category: ExpenseCategory, total: int}>
     */
    public function byCategory(): Collection
    {
        return $this->expenses()->selectRaw('category, sum(amount) as total')->groupBy('category')->get()
            ->map(fn (Expense $expense) => ['category' => $expense->category, 'total' => (int) $expense->getAttribute('total')])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Fees by how parents paid.
     *
     * @return array<string, int>
     */
    public function incomeByMethod(): array
    {
        return $this->payments()->selectRaw('method, sum(amount) as total')->groupBy('method')->get()
            ->mapWithKeys(fn (Payment $payment) => [$payment->method->label() => (int) $payment->getAttribute('total')])
            ->sortDesc()
            ->all();
    }

    /**
     * @return Builder<Payment>
     */
    private function payments(): Builder
    {
        return Payment::query()->where('status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()]);
    }

    /**
     * @return Builder<Expense>
     */
    private function expenses(): Builder
    {
        return Expense::query()->counted()->whereBetween('spent_on', [$this->from->toDateString(), $this->to->toDateString()]);
    }
}
