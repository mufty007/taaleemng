<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ConfirmSchoolSubscriptionPayment;
use App\Actions\Fees\ConfirmOnlinePayment;
use App\Actions\Fees\StartOnlinePayment;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolInvoice;
use App\Support\Payments\Paystack;
use App\Support\Platform\Health;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Paystack tells us about payments here, whether or not the parent came back to the site.
 *
 * The body is only a hint: the signature must match, and the payment is then
 * re-checked with Paystack's verify API before anything is confirmed.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        Paystack $paystack,
        ConfirmOnlinePayment $confirmFees,
        ConfirmSchoolSubscriptionPayment $confirmSubscription,
        CurrentSchool $currentSchool,
    ): Response {
        abort_unless($paystack->hasValidSignature($request->getContent(), $request->header('x-paystack-signature')), 401);

        Cache::forever(Health::PAYSTACK_WEBHOOK_KEY, now()->toIso8601String());

        if ($request->input('event') !== 'charge.success') {
            return response()->noContent();
        }

        $reference = (string) $request->input('data.reference');
        $transaction = $paystack->verify($reference);

        if (SchoolInvoice::query()->where('paystack_reference', $reference)->exists()) {
            $confirmSubscription->handle($reference, $transaction);

            return response()->noContent();
        }

        $schoolId = StartOnlinePayment::schoolIdFromReference($reference);
        $school = $schoolId ? School::query()->find($schoolId) : null;

        if ($school === null) {
            return response()->noContent();
        }

        $currentSchool->run($school, fn () => $confirmFees->handle($reference, $transaction));

        return response()->noContent();
    }
}
