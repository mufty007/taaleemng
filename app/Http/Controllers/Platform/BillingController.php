<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingModel;
use App\Enums\Plan;
use App\Enums\SchoolInvoiceStatus;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolInvoice;
use App\Models\User;
use App\Notifications\SchoolInvoiceNotification;
use App\Support\Billing\PlanCatalog;
use App\Support\Money;
use App\Support\Platform\Modules;
use App\Support\Platform\SchoolDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * What schools pay Ta'aleem: plans and prices, invoices to schools, payments received,
 * and which modules each school has.
 */
class BillingController extends Controller
{
    public function index(): View
    {
        $open = SchoolInvoice::query()->whereIn('status', [SchoolInvoiceStatus::Draft, SchoolInvoiceStatus::Sent]);
        $payingSchools = SchoolDirectory::query()->where('status', SchoolStatus::Active)->get();

        return view('platform.billing.index', [
            'stats' => [
                'paidThisMonth' => (int) SchoolInvoice::query()->where('status', SchoolInvoiceStatus::Paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'outstanding' => (int) (clone $open)->sum('amount'),
                'overdue' => (int) SchoolInvoice::query()->where('status', SchoolInvoiceStatus::Sent)->whereDate('due_on', '<', today())->sum('amount'),
                'termly' => (int) $payingSchools->sum(fn (School $school): int => (int) $school->students_count * (int) $school->pricePerStudent()),
            ],
            'byPlan' => School::query()->selectRaw('plan, count(*) as total')->groupBy('plan')->pluck('total', 'plan'),
            'openInvoices' => (clone $open)->with('school')->orderBy('due_on')->limit(30)->get(),
            'recentPayments' => SchoolInvoice::query()->where('status', SchoolInvoiceStatus::Paid)->with('school')->latest('paid_at')->limit(8)->get(),
        ]);
    }

    public function updatePlan(Request $request, School $school): RedirectResponse
    {
        $data = $request->validateWithBag('plan', [
            'plan' => ['required', Rule::enum(Plan::class)],
            'billing_model' => ['required', Rule::enum(BillingModel::class)],
            'price_per_student' => ['nullable', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
            'billing_cycle' => ['required', Rule::in(['termly', 'annual'])],
            'max_students' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_staff' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'paid_until' => ['nullable', 'date'],
            'billing_notes' => ['nullable', 'string', 'max:1000'],
            'apply_modules' => ['boolean'],
        ]);
        $plan = Plan::from($data['plan']);
        $billingModel = BillingModel::from($data['billing_model']);

        $school->update([
            'plan' => $plan,
            'billing_model' => $billingModel,
            'price_per_student' => filled($data['price_per_student'] ?? null) ? Money::toKobo($data['price_per_student']) : null,
            'billing_cycle' => $data['billing_cycle'],
            'max_students' => $data['max_students'] ?? null,
            'max_staff' => $data['max_staff'] ?? null,
            'paid_until' => $data['paid_until'] ?? null,
            'billing_notes' => $data['billing_notes'] ?? null,
            ...($request->boolean('apply_modules') ? ['disabled_modules' => $plan->disabledModules()] : []),
        ]);

        $this->log($school, "set the plan to {$plan->label()} ({$billingModel->label()})");

        return $this->backTo($school, 'billing', "{$school->name} is on the {$plan->label()} plan.");
    }

    public function updateModules(Request $request, School $school): RedirectResponse
    {
        $on = array_values(array_intersect((array) $request->input('modules', []), array_keys(Modules::ALL)));
        $off = array_values(array_diff(array_keys(Modules::ALL), $on));

        $school->update(['disabled_modules' => $off]);
        $this->log($school, 'changed the modules'.($off === [] ? ' (all on)' : ' (off: '.implode(', ', $off).')'));

        return $this->backTo($school, 'modules', 'Modules saved for '.$school->name.'.');
    }

    /**
     * Bill a school for a term or year: flat plan fee, or students × price per student.
     */
    public function storeInvoice(Request $request, School $school): RedirectResponse
    {
        $data = $request->validateWithBag('invoice', [
            'period' => ['required', 'string', 'max:80'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $students = (int) (SchoolDirectory::query()->whereKey($school->id)->value('students_count') ?? 0);
        $amount = PlanCatalog::invoiceAmount(
            $school->plan,
            $school->billing_model ?? BillingModel::Flat,
            $school->billing_cycle ?: 'termly',
            $students,
            $school->price_per_student,
        );

        if ($amount === null) {
            return $this->backTo($school, 'billing', 'Set a plan price (flat fee or per student) for this school first.');
        }

        $unit = ($school->billing_model ?? BillingModel::Flat) === BillingModel::PerStudent
            ? ($school->pricePerStudent() ?? 0)
            : $amount;

        $invoice = DB::transaction(function () use ($school, $data, $unit, $amount, $students, $request): SchoolInvoice {
            $year = now()->year;
            $sequence = SchoolInvoice::query()->whereYear('issued_on', $year)->lockForUpdate()->count() + 1;

            return SchoolInvoice::query()->create([
                'school_id' => $school->id,
                'number' => sprintf('TAL-%d-%04d', $year, $sequence),
                'period' => $data['period'],
                'billing_model' => ($school->billing_model ?? BillingModel::Flat)->value,
                'students' => $students,
                'unit_price' => $unit,
                'amount' => $amount,
                'status' => SchoolInvoiceStatus::Draft,
                'issued_on' => today(),
                'due_on' => $data['due_on'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        });

        $this->log($school, "issued invoice {$invoice->number} for ".Money::format($invoice->amount));

        return $this->backTo($school, 'billing', "Invoice {$invoice->number} for ".Money::format($invoice->amount).' created. Send it when ready.');
    }

    /**
     * Email the invoice to the school's admins (and the school's own email).
     */
    public function send(SchoolInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status->isOpen(), 422, 'Only an open invoice can be sent.');

        $school = $invoice->school;
        $admins = $this->schoolAdmins($school);
        Notification::send($admins, new SchoolInvoiceNotification($invoice));

        if (filled($school->email) && ! $admins->contains('email', $school->email)) {
            Notification::route('mail', $school->email)->notify(new SchoolInvoiceNotification($invoice));
        }

        $invoice->update(['status' => SchoolInvoiceStatus::Sent]);
        $this->log($school, "sent invoice {$invoice->number}");

        return $this->backTo($school, 'billing', "Invoice {$invoice->number} sent to {$school->name}’s admins.");
    }

    public function markPaid(Request $request, SchoolInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status->isOpen(), 422, 'This invoice is already settled.');

        $data = $request->validateWithBag('payment', [
            'payment_method' => ['required', Rule::in(['Bank transfer', 'Cash', 'Cheque', 'Paystack', 'Other'])],
            'payment_reference' => ['nullable', 'string', 'max:80'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $invoice->update([
            'status' => SchoolInvoiceStatus::Paid,
            'paid_at' => $data['paid_on'],
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
        ]);
        $this->log($invoice->school, "recorded payment of {$invoice->number} (".Money::format($invoice->amount).", {$data['payment_method']})");

        return $this->backTo($invoice->school, 'billing', "{$invoice->number} marked as paid.");
    }

    public function void(SchoolInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status->isOpen(), 422, 'Only an open invoice can be voided.');

        $invoice->update(['status' => SchoolInvoiceStatus::Void]);
        $this->log($invoice->school, "voided invoice {$invoice->number}");

        return $this->backTo($invoice->school, 'billing', "{$invoice->number} voided.");
    }

    public function print(SchoolInvoice $invoice): View
    {
        return view('platform.billing.invoice', ['invoice' => $invoice->load('school')]);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function schoolAdmins(School $school): EloquentCollection
    {
        return $school->users()
            ->whereNotNull('users.email')
            ->whereIn('users.id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.school_id', $school->id)
                ->where('roles.name', SchoolRole::SchoolAdmin->value)
                ->select('model_has_roles.model_id'))
            ->get();
    }

    private function log(School $school, string $description): void
    {
        activity('platform')->performedOn($school)->tap(fn ($activity) => $activity->school_id = $school->id)->log($description);
    }

    private function backTo(School $school, string $tab, string $message): RedirectResponse
    {
        return redirect()->to(route('platform.schools.show', $school).'#'.$tab)->with('status', $message);
    }
}
