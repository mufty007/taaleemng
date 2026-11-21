<?php

namespace App\Http\Controllers\School;

use App\Actions\Fees\AllocatePayments;
use App\Actions\Fees\GenerateInvoices;
use App\Actions\Fees\VoidInvoice;
use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Term;
use App\Support\Money;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Term invoices: issue them for the whole school, follow who has paid, adjust one.
 */
class InvoiceController extends Controller
{
    public const FILTERS = ['all' => 'All', 'unpaid' => 'Unpaid', 'part_paid' => 'Part paid', 'paid' => 'Paid', 'void' => 'Cancelled'];

    public function index(Request $request): View
    {
        $term = $this->selectedTerm($request);
        $filter = array_key_exists((string) $request->query('status'), self::FILTERS) ? (string) $request->query('status') : 'all';
        $armId = $request->integer('class') ?: null;
        $search = trim((string) $request->query('q'));

        $base = Invoice::query()->where('term_id', $term->id ?? 0);

        $summary = (clone $base)->where('status', '!=', InvoiceStatus::Void)
            ->selectRaw('count(*) as invoices, coalesce(sum(total), 0) as billed, coalesce(sum(amount_paid), 0) as collected')
            ->first();
        $statusCounts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $invoices = (clone $base)
            ->with(['student', 'enrolment.classArm.level'])
            ->when($filter !== 'all', fn (Builder $query) => $query->where('status', $filter))
            ->when($armId, fn (Builder $query) => $query->whereHas('enrolment', fn (Builder $query) => $query->where('class_arm_id', $armId)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('student', fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_no', 'like', "%{$search}%"))))
            ->orderByRaw('total - amount_paid desc')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        $unbilled = $term ? Enrolment::query()
            ->where('academic_session_id', $term->academic_session_id)
            ->where('status', EnrolmentStatus::Active)
            ->whereDoesntHave('student.invoices', fn (Builder $query) => $query->where('term_id', $term->id))
            ->count() : 0;

        return view('school.invoices.index', [
            'term' => $term,
            'terms' => Term::options(),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'statusCounts' => $statusCounts,
            'summary' => [
                'invoices' => (int) ($summary->invoices ?? 0),
                'billed' => (int) ($summary->billed ?? 0),
                'collected' => (int) ($summary->collected ?? 0),
            ],
            'unbilled' => $unbilled,
            'invoices' => $invoices,
            'search' => $search,
            'armId' => $armId,
            'arms' => ClassArm::query()->with('level')->get()->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))->mapWithKeys(fn (ClassArm $arm) => [$arm->id => $arm->displayName()]),
            'levels' => ClassLevel::query()->orderBy('position')->pluck('name', 'id'),
        ]);
    }

    public function generate(Request $request, GenerateInvoices $generate): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', TenantRule::exists('terms')],
            'class_level_ids' => ['nullable', 'array'],
            'class_level_ids.*' => ['integer', TenantRule::exists('class_levels', 'id')],
            'due_on' => ['nullable', 'date'],
        ]);

        $term = Term::query()->findOrFail($data['term_id']);
        $levelIds = array_values(array_map('intval', $data['class_level_ids'] ?? [])) ?: null;
        $counts = $generate->handle($term, $levelIds, isset($data['due_on']) ? Carbon::parse($data['due_on']) : null);

        $message = "{$counts['created']} ".str('invoice')->plural($counts['created'])." issued for {$term->name}.";

        if ($counts['existing']) {
            $message .= " {$counts['existing']} students already had one.";
        }

        if ($counts['unpriced']) {
            $message .= " {$counts['unpriced']} students were skipped because their class has no fees set for the term.";
        }

        return to_route('school.invoices.index', ['term' => $term->id])->with('status', $message);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['student.guardians', 'enrolment.classArm.level', 'term.academicSession', 'lines', 'allocations.payment.recorder']);

        return view('school.invoices.show', [
            'invoice' => $invoice,
            'studentBalance' => AllocatePayments::balance($invoice->student),
            'methods' => collect(PaymentMethod::offline())->mapWithKeys(fn (PaymentMethod $method) => [$method->value => $method->label()])->all(),
        ]);
    }

    /**
     * Add an extra charge or a discount to one invoice.
     */
    public function addLine(Request $request, Invoice $invoice, AllocatePayments $allocate): RedirectResponse
    {
        abort_if($invoice->status === InvoiceStatus::Void, 409);

        $data = $request->validateWithBag('addLine', [
            'kind' => ['required', Rule::in(['charge', 'discount'])],
            'description' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
        ], ['amount.regex' => 'Enter an amount like 15,000.']);

        $amount = Money::toKobo($data['amount']);
        $signed = $data['kind'] === 'discount' ? -$amount : $amount;

        DB::transaction(function () use ($invoice, $data, $signed): void {
            $invoice->lines()->create(['description' => $data['description'], 'amount' => $signed]);
            $invoice->update(['total' => (int) $invoice->lines()->sum('amount')]);
        });

        $allocate->rebalance($invoice->fresh());

        activity('fees')->performedOn($invoice)->log(($data['kind'] === 'discount' ? 'gave a discount of ' : 'added a charge of ').Money::format($amount)." ({$data['description']}) on {$invoice->invoice_no}");

        return back()->with('status', ($data['kind'] === 'discount' ? 'Discount' : 'Charge').' added. The invoice total is now '.Money::format($invoice->fresh()->total).'.');
    }

    public function void(Request $request, Invoice $invoice, VoidInvoice $voidInvoice): RedirectResponse
    {
        $data = $request->validateWithBag('void', ['reason' => ['required', 'string', 'max:200']]);

        $voidInvoice->handle($invoice, $request->user(), $data['reason']);

        return back()->with('status', "Invoice {$invoice->invoice_no} cancelled. Anything paid on it is now credit for the student.");
    }

    private function selectedTerm(Request $request): ?Term
    {
        $termId = $request->integer('term');

        return $termId ? Term::query()->with('academicSession')->find($termId) : Term::current();
    }
}
