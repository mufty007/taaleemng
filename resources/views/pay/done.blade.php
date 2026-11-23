@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;

    $status = $payment?->status;
@endphp

<x-layouts.school-public title="Payment" :school="$school">
    <section class="flex flex-col items-center gap-4 rounded-3xl border border-line bg-white px-6 py-12 text-center" aria-live="polite">
        @if ($status === PaymentStatus::Confirmed)
            <span class="grid size-16 place-items-center rounded-full bg-naija-50 text-naija"><x-lucide-check class="size-8" aria-hidden="true" /></span>
            <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">Payment received</h1>
            <p class="m-0 max-w-sm text-muted">Thank you. {{ Money::format($payment->amount) }} for {{ $student?->fullName() }} has reached {{ $school->name }}.</p>
            <dl class="m-0 grid w-full max-w-xs grid-cols-2 gap-3 text-left">
                <div class="rounded-2xl bg-canvas p-3">
                    <dt class="text-xs text-muted">Receipt</dt>
                    <dd class="m-0 font-mono text-sm font-semibold">{{ $payment->receipt_no }}</dd>
                </div>
                <div class="rounded-2xl bg-canvas p-3">
                    <dt class="text-xs text-muted">Paid</dt>
                    <dd class="m-0 text-sm font-semibold">{{ $payment->paid_at?->format('j M Y, g:ia') }}</dd>
                </div>
            </dl>
            <p class="m-0 text-sm text-muted">Paystack has emailed you a receipt. The school’s copy is on {{ $student?->first_name }}’s record.</p>
        @elseif ($status === PaymentStatus::Failed)
            <span class="grid size-16 place-items-center rounded-full bg-danger-50 text-danger"><x-lucide-x class="size-8" aria-hidden="true" /></span>
            <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">The payment did not go through</h1>
            <p class="m-0 max-w-sm text-muted">Nothing was taken from your account. Go back to the link in your reminder to try again, or pay at the school.</p>
        @else
            <span class="grid size-16 place-items-center rounded-full bg-gold-50 text-gold"><x-lucide-hourglass class="size-8" aria-hidden="true" /></span>
            <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">Waiting for confirmation</h1>
            <p class="m-0 max-w-sm text-muted">We are waiting for Paystack to confirm your payment. If money left your account, the school will see it within a few minutes; there is no need to pay again. If you didn’t finish paying, open the link in your reminder to try again.</p>
        @endif
    </section>
</x-layouts.school-public>
