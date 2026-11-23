<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Fees\ConfirmOnlinePayment;
use App\Actions\Fees\StartOnlinePayment;
use App\Http\Controllers\Controller;
use App\Models\School;
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
    public function __invoke(Request $request, Paystack $paystack, ConfirmOnlinePayment $confirm, CurrentSchool $currentSchool): Response
    {
        abort_unless($paystack->hasValidSignature($request->getContent(), $request->header('x-paystack-signature')), 401);

        // The Health page shows when Paystack last reached us, so a broken webhook URL is noticed.
        Cache::forever(Health::PAYSTACK_WEBHOOK_KEY, now()->toIso8601String());

        $reference = (string) $request->input('data.reference');
        $schoolId = StartOnlinePayment::schoolIdFromReference($reference);
        $school = $schoolId ? School::query()->find($schoolId) : null;

        // Other events, and payments that are not ours, are acknowledged and ignored.
        if ($request->input('event') !== 'charge.success' || $school === null) {
            return response()->noContent();
        }

        $transaction = $paystack->verify($reference);
        $currentSchool->run($school, fn () => $confirm->handle($reference, $transaction));

        return response()->noContent();
    }
}
