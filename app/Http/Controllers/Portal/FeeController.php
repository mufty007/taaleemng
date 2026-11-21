<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Fees\ConfirmOnlinePayment;
use App\Actions\Fees\StartOnlinePayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Money;
use App\Support\Payments\Paystack;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Parents pay school fees online with Paystack.
 */
class FeeController extends Controller
{
    public function pay(Request $request, Invoice $invoice, StartOnlinePayment $start, Paystack $paystack): RedirectResponse
    {
        $guardian = $request->user()->guardianRecord();

        abort_unless($guardian?->students()->whereKey($invoice->student_id)->exists(), 403);
        abort_unless($paystack->isConfigured(), 404);

        try {
            return redirect()->away($start->handle($invoice->load('student'), $request->user()));
        } catch (ValidationException $exception) {
            return back()->with('status', $exception->validator->errors()->first());
        } catch (RequestException) {
            return back()->with('status', 'Online payment is not available right now. Please try again in a few minutes, or pay at the school.');
        }
    }

    /**
     * Paystack sends the parent back here. Check with Paystack before saying anything.
     */
    public function callback(Request $request, Paystack $paystack, ConfirmOnlinePayment $confirm): RedirectResponse
    {
        $reference = (string) $request->query('reference');

        try {
            $payment = $confirm->handle($reference, $paystack->verify($reference));
        } catch (RequestException) {
            $payment = null;
        }

        $child = $payment?->student_id ? $payment->student()->first() : null;
        $destination = $child ? route('school.portal.child', $child).'#fees' : route('school.portal');

        return redirect($destination)->with('status', match ($payment?->status) {
            PaymentStatus::Confirmed => 'Thank you. '.Money::format($payment->amount).' received, receipt '.$payment->receipt_no.'.',
            PaymentStatus::Failed => 'The payment did not go through. Nothing was taken; you can try again.',
            default => 'We are waiting for Paystack to confirm your payment. It will show here within a few minutes.',
        });
    }
}
