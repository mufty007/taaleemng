<?php

namespace App\Http\Controllers\School;

use App\Actions\Billing\ConfirmSchoolSubscriptionPayment;
use App\Actions\Billing\StartSchoolSubscriptionPayment;
use App\Http\Controllers\Controller;
use App\Models\SchoolInvoice;
use App\Support\Money;
use App\Support\Payments\Paystack;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
