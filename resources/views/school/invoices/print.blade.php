@php
    use App\Enums\InvoiceStatus;
    use App\Enums\PaymentStatus;
    use App\Support\Money;
    $school = $currentSchool;
    $student = $invoice->student;
    $guardian = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
    $payments = $invoice->allocations->filter(fn ($allocation) => $allocation->payment->status === PaymentStatus::Confirmed);
@endphp

<x-layouts.print :title="'Invoice '.$invoice->invoice_no" document="School fees invoice" :school="$school" width="max-w-[800px]">
    @if ($invoice->status === InvoiceStatus::Void)
        <div class="pointer-events-none absolute inset-0 grid place-items-center" aria-hidden="true">
            <span class="-rotate-12 rounded-2xl border-4 border-danger px-6 py-2 text-5xl font-black tracking-widest text-danger/40 uppercase">Cancelled</span>
        </div>
    @elseif ($invoice->status === InvoiceStatus::Paid)
        <div class="pointer-events-none absolute inset-0 grid place-items-center" aria-hidden="true">
            <span class="-rotate-12 rounded-2xl border-4 border-naija px-6 py-2 text-5xl font-black tracking-widest text-naija/30 uppercase">Paid</span>
        </div>
    @endif

    <x-slot:meta>
        <div class="mt-1.5 font-mono text-lg font-bold">{{ $invoice->invoice_no }}</div>
        <div class="text-sm text-muted">Issued {{ $invoice->issued_at->format('j F Y') }}</div>
        @if ($invoice->due_on)<div class="text-sm font-semibold">Pay by {{ $invoice->due_on->format('j F Y') }}</div>@endif
    </x-slot:meta>

    <section class="grid grid-cols-2 gap-x-8 gap-y-3 py-5 text-[15px] sm:grid-cols-3">
        <div><div class="text-sm text-muted">Billed to</div><div class="font-semibold">{{ $guardian?->fullName() ?? 'Parent / guardian' }}</div>@if ($guardian)<div class="text-sm text-muted">{{ $guardian->phone }}</div>@endif</div>
        <div><div class="text-sm text-muted">Student</div><div class="font-semibold">{{ $student->fullName() }}</div><div class="font-mono text-sm text-muted">{{ $student->admission_no }}</div></div>
        <div><div class="text-sm text-muted">Class &amp; term</div><div class="font-semibold">{{ $invoice->enrolment->classArm->displayName() }}</div><div class="text-sm text-muted">{{ $invoice->term->name }}, {{ $invoice->term->academicSession->name }}</div></div>
    </section>

    <table class="w-full border-collapse text-left text-[15px]">
        <thead>
            <tr class="bg-brand-600 text-xs font-semibold text-white uppercase">
                <th scope="col" class="w-10 px-3 py-2">#</th>
                <th scope="col" class="px-3 py-2">Item</th>
                <th scope="col" class="px-3 py-2 text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $index => $line)
                <tr class="border-b border-line">
                    <td class="px-3 py-2 text-muted">{{ $index + 1 }}</td>
                    <td class="px-3 py-2">{{ $line->description }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ Money::format($line->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th scope="row" colspan="2" class="px-3 pt-3 text-right font-semibold">Total</th>
                <td class="px-3 pt-3 text-right font-mono font-bold">{{ Money::format($invoice->total) }}</td>
            </tr>
            <tr>
                <th scope="row" colspan="2" class="px-3 pt-1 text-right font-normal text-muted">Paid</th>
                <td class="px-3 pt-1 text-right font-mono text-muted">{{ Money::format($invoice->amount_paid) }}</td>
            </tr>
            <tr>
                <th scope="row" colspan="2" class="px-3 pt-1 text-right font-semibold">Balance due</th>
                <td class="px-3 pt-1 text-right font-mono text-lg font-bold text-brand-700">{{ Money::format($invoice->balance()) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-4 rounded-xl bg-brand-50 px-4 py-3 text-[15px]"><span class="text-sm text-muted">Amount in words</span> <span class="font-semibold">{{ Money::inWords($invoice->total) }}</span></div>

    @if ($payments->isNotEmpty())
        <h2 class="m-0 mt-5 text-sm font-semibold tracking-wide text-brand-700 uppercase">Payments received</h2>
        <ul class="m-0 mt-1 list-none p-0 text-sm">
            @foreach ($payments as $allocation)
                <li class="flex justify-between border-b border-line py-1.5">
                    <span>{{ $allocation->payment->paid_at?->format('j M Y') }} · {{ $allocation->payment->method->label() }} · <span class="font-mono">{{ $allocation->payment->receipt_no }}</span></span>
                    <span class="font-mono">{{ Money::format($allocation->amount) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($studentBalance > $invoice->balance())
        <p class="m-0 mt-4 text-sm text-muted">Outstanding from earlier terms is also due: total owed {{ Money::format($studentBalance) }}.</p>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
        <div class="rounded-xl border border-line p-4">
            <div class="text-xs font-semibold tracking-wide text-muted uppercase">How to pay</div>
            <p class="m-0 mt-1.5">Pay online from the parents’ portal, or at the bursary by cash, transfer or POS. Quote the invoice number <span class="font-mono font-semibold">{{ $invoice->invoice_no }}</span>.</p>
            @if ($invoice->notes)<p class="m-0 mt-2 whitespace-pre-line">{{ $invoice->notes }}</p>@endif
        </div>
        <div class="flex flex-col justify-end rounded-xl border border-line p-4">
            <span class="mt-8 border-t border-dashed border-line pt-1 text-center text-xs text-muted">Bursar’s signature &amp; stamp</span>
        </div>
    </div>
</x-layouts.print>
