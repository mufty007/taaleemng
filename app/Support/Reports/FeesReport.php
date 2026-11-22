<?php

namespace App\Support\Reports;

use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\FeeItem;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Term;
use App\Models\User;
use App\Support\Finance\CashflowPeriod;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A term's fees: what was billed, how much of it has come in, class by class and item by item,
 * how old the school's unpaid debt is, and who took the money.
 *
 * Billed and collected figures are for the term's own invoices (void ones left out). Cash
 * figures (by week, method and recorder) are payments received in the term's cashflow window,
 * whichever term they paid for.
 */
final class FeesReport implements SchoolReport
{
    /** @var EloquentCollection<int, Invoice>|null */
    private ?EloquentCollection $invoices = null;

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
        $invoices = $this->invoices();
        $billed = (int) $invoices->sum('total');
        $paid = (int) $invoices->sum('amount_paid');
        $payments = $this->payments();

        return [
            'term' => $this->term,
            'period' => $this->period,
            'stats' => [
                'billed' => $billed,
                'paid' => $paid,
                'outstanding' => (int) $invoices->sum(fn (Invoice $invoice) => $invoice->balance()),
                'rate' => $billed > 0 ? (int) round($paid / $billed * 100) : null,
                'received' => (int) $payments->sum('amount'),
                'receipts' => $payments->count(),
                'invoices' => $invoices->count(),
                'fully_paid' => $invoices->where('status', InvoiceStatus::Paid)->count(),
                'part_paid' => $invoices->where('status', InvoiceStatus::PartPaid)->count(),
                'unpaid' => $invoices->where('status', InvoiceStatus::Unpaid)->count(),
                'not_invoiced' => $this->notInvoiced(),
            ],
            'classes' => $this->classRows(),
            'items' => $this->itemRows(),
            'ageing' => $this->ageing(),
            'weeks' => $this->weeks($payments),
            'methods' => $payments->groupBy(fn (Payment $payment): string => $payment->method->label())->map(fn (Collection $group) => (int) $group->sum('amount'))->sortDesc()->all(),
            'recorders' => $this->recorders($payments),
            'largest' => $invoices->filter(fn (Invoice $invoice) => $invoice->balance() > 0)->sortByDesc(fn (Invoice $invoice) => $invoice->balance())->take(10)->values(),
        ];
    }

    public function csv(): StreamedResponse
    {
        $invoices = $this->invoices()->load(['student.guardians' => fn ($query) => $query->wherePivot('is_primary', true)]);

        $rows = $invoices
            ->sortBy(fn (Invoice $invoice) => EnrolmentReport::armSortKey($invoice->enrolment->classArm).' '.$invoice->student->last_name.' '.$invoice->student->first_name)
            ->map(function (Invoice $invoice): array {
                /** @var Guardian|null $guardian */
                $guardian = $invoice->student->guardians->first();

                return [
                    $invoice->enrolment->classArm->displayName(),
                    $invoice->student->admission_no,
                    $invoice->student->registerName(),
                    $invoice->invoice_no,
                    Money::toNaira($invoice->total),
                    Money::toNaira($invoice->amount_paid),
                    Money::toNaira($invoice->balance()),
                    $invoice->status->label(),
                    $invoice->due_on?->format('Y-m-d'),
                    $guardian?->fullName(),
                    $guardian?->phone,
                ];
            });

        return CsvDownload::make(
            'fees-'.str($this->term->name.' '.$this->term->academicSession->name)->slug().'.csv',
            ['Class', 'Admission no.', 'Student', 'Invoice no.', 'Billed (₦)', 'Paid (₦)', 'Balance (₦)', 'Status', 'Due', 'Parent / guardian', 'Phone'],
            $rows,
        );
    }

    /**
     * The term's invoices, leaving out void ones.
     *
     * @return EloquentCollection<int, Invoice>
     */
    private function invoices(): EloquentCollection
    {
        return $this->invoices ??= Invoice::query()
            ->where('term_id', $this->term->id)
            ->where('status', '!=', InvoiceStatus::Void)
            ->whereHas('student')
            ->with(['student', 'enrolment.classArm.level'])
            ->get();
    }

    /**
     * Confirmed payments received in the term's cashflow window.
     *
     * @return Collection<int, Payment>
     */
    private function payments(): Collection
    {
        return Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$this->period->from->copy()->startOfDay(), $this->period->to->copy()->endOfDay()])
            ->get(['id', 'amount', 'method', 'paid_at', 'recorded_by']);
    }

    /**
     * Students on roll this session who have no invoice for the term.
     */
    private function notInvoiced(): int
    {
        return Enrolment::query()
            ->where('academic_session_id', $this->term->academic_session_id)
            ->where('status', '!=', EnrolmentStatus::Withdrawn)
            ->whereHas('student')
            ->whereNotIn('id', $this->invoices()->pluck('enrolment_id'))
            ->count();
    }

    /**
     * @return Collection<int, array{arm: ClassArm, invoices: int, billed: int, paid: int, outstanding: int, rate: ?int, fully_paid: int, owing: int}>
     */
    private function classRows(): Collection
    {
        $byArm = $this->invoices()->groupBy(fn (Invoice $invoice): int => $invoice->enrolment->class_arm_id);

        return ClassArm::query()
            ->with('level')
            ->whereIn('id', $byArm->keys())
            ->get()
            ->sortBy(fn (ClassArm $arm) => EnrolmentReport::armSortKey($arm))
            ->values()
            ->map(function (ClassArm $arm) use ($byArm): array {
                $group = $byArm->get($arm->id, new EloquentCollection);
                $billed = (int) $group->sum('total');
                $paid = (int) $group->sum('amount_paid');

                return [
                    'arm' => $arm,
                    'invoices' => $group->count(),
                    'billed' => $billed,
                    'paid' => $paid,
                    'outstanding' => (int) $group->sum(fn (Invoice $invoice) => $invoice->balance()),
                    'rate' => $billed > 0 ? (int) round($paid / $billed * 100) : null,
                    'fully_paid' => $group->where('status', InvoiceStatus::Paid)->count(),
                    'owing' => $group->filter(fn (Invoice $invoice) => $invoice->balance() > 0)->count(),
                ];
            });
    }

    /**
     * What each fee item adds up to on the term's invoices. Negative lines are discounts and waivers.
     *
     * @return Collection<int, array{name: string, students: int, amount: int, discount: bool}>
     */
    private function itemRows(): Collection
    {
        if ($this->invoices()->isEmpty()) {
            return collect();
        }

        $lines = InvoiceLine::query()
            ->whereIn('invoice_id', $this->invoices()->modelKeys())
            ->selectRaw('fee_item_id, description, amount < 0 as is_discount, count(*) as line_count, sum(amount) as total')
            ->groupBy('fee_item_id', 'description', 'is_discount')
            ->get();

        $items = FeeItem::query()->whereIn('id', $lines->pluck('fee_item_id')->filter())->get()->keyBy('id');

        return $lines
            ->groupBy(fn (InvoiceLine $line): string => ($line->fee_item_id ? 'item-'.$line->fee_item_id : 'line-'.mb_strtolower($line->description)).($line->getAttribute('is_discount') ? '-discount' : ''))
            ->map(fn (Collection $group) => [
                'name' => $items->get($group->first()->fee_item_id)->name ?? $group->first()->description,
                'students' => (int) $group->sum(fn (InvoiceLine $line) => (int) $line->getAttribute('line_count')),
                'amount' => (int) $group->sum(fn (InvoiceLine $line) => (int) $line->getAttribute('total')),
                'discount' => (bool) $group->first()->getAttribute('is_discount'),
            ])
            ->sortBy([['discount', 'asc'], ['amount', 'desc']])
            ->values();
    }

    /**
     * Every unpaid balance in the school today, by how long it has been overdue.
     *
     * @return list<array{label: string, invoices: int, amount: int}>
     */
    private function ageing(): array
    {
        $buckets = [
            'Not yet due' => ['invoices' => 0, 'amount' => 0],
            '1–30 days overdue' => ['invoices' => 0, 'amount' => 0],
            '31–60 days overdue' => ['invoices' => 0, 'amount' => 0],
            '61–90 days overdue' => ['invoices' => 0, 'amount' => 0],
            'Over 90 days overdue' => ['invoices' => 0, 'amount' => 0],
        ];

        $open = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
            ->get(['id', 'total', 'amount_paid', 'status', 'due_on']);

        foreach ($open as $invoice) {
            $balance = $invoice->balance();

            if ($balance <= 0) {
                continue;
            }

            $overdue = $invoice->due_on === null || $invoice->due_on->gte(today()) ? 0 : (int) $invoice->due_on->diffInDays(today());
            $bucket = match (true) {
                $overdue === 0 => 'Not yet due',
                $overdue <= 30 => '1–30 days overdue',
                $overdue <= 60 => '31–60 days overdue',
                $overdue <= 90 => '61–90 days overdue',
                default => 'Over 90 days overdue',
            };
            $buckets[$bucket]['invoices']++;
            $buckets[$bucket]['amount'] += $balance;
        }

        return array_map(fn (string $label, array $row) => ['label' => $label, ...$row], array_keys($buckets), $buckets);
    }

    /**
     * Money received each week of the cashflow window, up to today.
     *
     * @param  Collection<int, Payment>  $payments
     * @return list<array{label: string, starts: Carbon, amount: int}>
     */
    private function weeks(Collection $payments): array
    {
        $weeks = [];
        $last = $this->period->to->copy()->min(today());

        for ($week = $this->period->from->copy()->startOfWeek(); $week->lte($last); $week->addWeek()) {
            $end = $week->copy()->endOfWeek();
            $weeks[] = [
                'label' => $week->format('j M'),
                'starts' => $week->copy(),
                'amount' => (int) $payments->filter(fn (Payment $payment) => $payment->paid_at?->betweenIncluded($week, $end))->sum('amount'),
            ];
        }

        return $weeks;
    }

    /**
     * Who received or recorded the money: each staff member, and Paystack for online payments.
     *
     * @param  Collection<int, Payment>  $payments
     * @return list<array{name: string, payments: int, amount: int}>
     */
    private function recorders(Collection $payments): array
    {
        $names = User::query()->whereIn('id', $payments->pluck('recorded_by')->filter()->unique())->pluck('name', 'id');

        return $payments
            ->groupBy(fn (Payment $payment): string => $payment->recorded_by
                ? ($names[$payment->recorded_by] ?? 'Former user')
                : ($payment->method === PaymentMethod::Paystack ? 'Paid online (Paystack)' : 'Not recorded'))
            ->map(fn (Collection $group, string $name) => ['name' => $name, 'payments' => $group->count(), 'amount' => (int) $group->sum('amount')])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }
}
