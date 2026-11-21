<?php

namespace App\Http\Controllers\School;

use App\Actions\Fees\RecordPayment;
use App\Actions\Fees\ReversePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Money received: the collections list, recording a payment, receipts and reversals.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth();
        $to = $this->date($request->query('to')) ?? now();
        $method = PaymentMethod::tryFrom((string) $request->query('method'));
        $search = trim((string) $request->query('q'));

        $confirmed = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $byMethod = (clone $confirmed)->selectRaw('method, count(*) as payments, sum(amount) as total')->groupBy('method')->get()->keyBy('method');

        $payments = Payment::query()
            ->with(['student', 'recorder'])
            ->where(fn (Builder $query) => $query
                ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->orWhere(fn (Builder $query) => $query->whereNull('paid_at')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])))
            ->when($method, fn (Builder $query) => $query->where('method', $method))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('receipt_no', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhereHas('student', fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_no', 'like', "%{$search}%"))))
            ->latest('paid_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('school.payments.index', [
            'payments' => $payments,
            'from' => $from,
            'to' => $to,
            'method' => $method,
            'search' => $search,
            'methods' => PaymentMethod::cases(),
            'byMethod' => $byMethod,
            'total' => (int) $byMethod->sum('total'),
            'count' => (int) $byMethod->sum('payments'),
        ]);
    }

    public function store(Request $request, Student $student, RecordPayment $record): RedirectResponse
    {
        $data = $request->validateWithBag('payment', [
            'amount' => ['required', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::offline())],
            'paid_at' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYear()->toDateString()],
            'reference' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => in_array($request->input('method'), [PaymentMethod::Transfer->value, PaymentMethod::Cheque->value], true))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.regex' => 'Enter an amount like 150,000.',
            'reference.required' => 'Add the transfer or cheque reference, so the payment can be traced.',
            'paid_at.before_or_equal' => 'The payment date can’t be in the future.',
        ]);

        $amount = Money::toKobo($data['amount']);

        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'Enter an amount above zero.'], 'payment')->withInput();
        }

        $payment = $record->handle(
            $student,
            $amount,
            PaymentMethod::from($data['method']),
            Carbon::parse($data['paid_at'])->setTimeFrom(now()),
            $request->user(),
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with('status', Money::format($amount)." recorded for {$student->fullName()}. Receipt {$payment->receipt_no}.")
            ->with('receipt', route('school.payments.receipt', $payment));
    }

    /**
     * A printable receipt. Staff with finance access, and the student's family, can see it.
     */
    public function receipt(Request $request, Payment $payment): View
    {
        $user = $request->user();
        $payment->load(['student.guardians', 'allocations.invoice.term.academicSession', 'recorder', 'guardian']);
        $isFamily = $user->guardianRecord()?->students()->whereKey($payment->student_id)->exists() || $user->studentRecord()?->is($payment->student);

        abort_unless($user->can('finance.view') || $user->can('payments.record') || $isFamily, 403);
        abort_unless($payment->status !== PaymentStatus::Pending && $payment->status !== PaymentStatus::Failed, 404);

        return view('school.payments.receipt', ['payment' => $payment]);
    }

    public function reverse(Request $request, Payment $payment, ReversePayment $reverse): RedirectResponse
    {
        $data = $request->validateWithBag('reverse', ['reason' => ['required', 'string', 'max:200']]);

        $reverse->handle($payment, $request->user(), $data['reason']);

        return back()->with('status', "Receipt {$payment->receipt_no} reversed. The invoices it paid are open again.");
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
