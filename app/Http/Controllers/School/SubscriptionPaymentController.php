<?php

namespace App\Http\Controllers\School;

use App\Actions\Billing\ChooseSchoolPlan;
use App\Actions\Billing\ConfirmSchoolSubscriptionPayment;
use App\Actions\Billing\StartSchoolSubscriptionPayment;
use App\Enums\BillingModel;
use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Models\SchoolInvoice;
use App\Support\Money;
use App\Support\Payments\Paystack;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * School admins pay Ta'aleem subscription invoices online with Paystack.
 */
class SubscriptionPaymentController extends Controller
{
    public function pay(
        Request $request,
        SchoolInvoice $invoice,
        CurrentSchool $currentSchool,
        StartSchoolSubscriptionPayment $start,
        Paystack $paystack,
    ): RedirectResponse {
        abort_unless($invoice->school_id === $currentSchool->getOrFail()->id, 404);
        abort_unless($request->user()->can('school.manage'), 403);
        abort_unless($paystack->isConfigured(), 404);

        try {
            return redirect()->away($start->handle($invoice, $request->user()));
        } catch (ValidationException $exception) {
            return back()->with('status', $exception->validator->errors()->first());
        } catch (RequestException) {
            return back()->with('status', 'Online payment is not available right now. Try again shortly, or pay by transfer.');
        }
    }

    /**
     * The school picks a plan and is sent to Paystack to pay for it.
     */
    public function checkout(
        Request $request,
        CurrentSchool $currentSchool,
        ChooseSchoolPlan $choosePlan,
        StartSchoolSubscriptionPayment $start,
        Paystack $paystack,
    ): RedirectResponse {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_map(fn (Plan $plan) => $plan->value, Plan::publicTiers()))],
            'billing_model' => ['required', Rule::enum(BillingModel::class)],
            'billing_cycle' => ['required', Rule::in(['termly', 'annual'])],
        ]);

        if (! $paystack->isConfigured()) {
            return redirect()->to(route('school.settings.edit').'#subscription')
                ->with('status', 'Online payment is not switched on yet. Email '.config('taaleem.marketing.contact_email').' to subscribe.');
        }

        $invoice = $choosePlan->handle(
            $currentSchool->getOrFail(),
            Plan::from($data['plan']),
            BillingModel::from($data['billing_model']),
            $data['billing_cycle'],
            $request->user(),
        );

        try {
            return redirect()->away($start->handle($invoice, $request->user()));
        } catch (RequestException) {
            return redirect()->to(route('school.settings.edit').'#subscription')
                ->with('status', 'Paystack could not be reached. Your invoice '.$invoice->number.' is saved — try “Pay online” again shortly.');
        }
    }

    public function callback(Request $request, Paystack $paystack, ConfirmSchoolSubscriptionPayment $confirm): RedirectResponse
    {
        $reference = (string) $request->query('reference');

        try {
            $invoice = $confirm->handle($reference, $paystack->verify($reference));
        } catch (RequestException) {
            $invoice = null;
        }

        return redirect()->to(route('school.settings.edit').'#subscription')->with('status', match (true) {
            $invoice?->status->value === 'paid' => 'Thank you. '.Money::format($invoice->amount).' received for '.$invoice->number.'.',
            default => 'We are waiting for Paystack to confirm the payment. It will show here within a few minutes.',
        });
    }
}
