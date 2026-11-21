@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
    $school = $currentSchool;
    $student = $payment->student;
@endphp

<x-layouts.print :title="'Receipt '.$payment->receipt_no" document="Official receipt" :school="$school" page="A5 landscape" width="max-w-[760px]">
    @if ($payment->status === PaymentStatus::Reversed)
        <div class="pointer-events-none absolute inset-0 grid place-items-center" aria-hidden="true">
            <span class="-rotate-12 rounded-2xl border-4 border-danger px-6 py-2 text-5xl font-black tracking-widest text-danger/40 uppercase">Reversed</span>
        </div>
    @endif

    <x-slot:meta>
        <div class="mt-1.5 font-mono text-lg font-bold">{{ $payment->receipt_no }}</div>
        <div class="text-sm text-muted">{{ $payment->paid_at?->format('j F Y') }}</div>
    </x-slot:meta>

        <section class="grid grid-cols-2 gap-x-8 gap-y-3 py-5 text-[15px]">
            <div><div class="text-sm text-muted">Received from</div><div class="font-semibold">{{ $payment->guardian?->fullName() ?? 'Parent of '.$student->first_name }}</div></div>
            <div><div class="text-sm text-muted">For</div><div class="font-semibold">{{ $student->fullName() }} <span class="font-mono text-sm font-normal text-muted">({{ $student->admission_no }})</span></div></div>
            <div><div class="text-sm text-muted">Paid by</div><div class="font-semibold">{{ $payment->method->label() }}{{ $payment->reference && $payment->method !== \App\Enums\PaymentMethod::Paystack ? ' · '.$payment->reference : '' }}</div></div>
            <div><div class="text-sm text-muted">Amount</div><div class="text-2xl font-bold text-brand-700">{{ Money::format($payment->amount) }}</div></div>
            <div class="col-span-2 rounded-xl bg-brand-50 px-4 py-3"><span class="text-sm text-muted">The sum of</span> <span class="font-semibold">{{ Money::inWords($payment->amount) }}</span></div>
        </section>

        @if ($payment->allocations->isNotEmpty())
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b-2 border-brand-200 text-xs font-semibold text-brand-700 uppercase">
                        <th scope="col" class="py-2">Applied to</th>
                        <th scope="col" class="py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payment->allocations as $allocation)
                        <tr class="border-b border-line">
                            <td class="py-2">{{ $allocation->invoice->term->name }} {{ $allocation->invoice->term->academicSession->name }} fees <span class="font-mono text-xs text-muted">{{ $allocation->invoice->invoice_no }}</span></td>
                            <td class="py-2 text-right font-mono">{{ Money::format($allocation->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="mt-8 flex items-end justify-between gap-6 text-sm text-muted">
            <span>{{ $payment->method === \App\Enums\PaymentMethod::Paystack ? 'Paid online and confirmed by Paystack.' : 'Received by '.($payment->recorder?->name ?? 'the bursary').'.' }}</span>
            <span class="w-48 border-t border-dashed border-line pt-1 text-center">Bursar’s signature &amp; stamp</span>
        </div>
</x-layouts.print>
