@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
    $school = $currentSchool;
    $student = $payment->student;
@endphp

<x-layouts.base :title="'Receipt '.$payment->receipt_no">
    <x-slot:head>
        <style>
            @media print {
                @page { size: A5 landscape; margin: 10mm; }
                .no-print { display: none !important; }
                body { background: white !important; }
                .sheet { box-shadow: none !important; border: 0 !important; margin: 0 !important; padding: 0 !important; }
            }
        </style>
    </x-slot:head>

    <div class="no-print sticky top-0 z-10 border-b border-line bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-[760px] items-center justify-between gap-3 px-4 py-3">
            <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-ink"><x-lucide-arrow-left class="size-4" aria-hidden="true" /> Back</a>
            <x-button icon="printer" type="button" onclick="window.print()">Print or save as PDF</x-button>
        </div>
    </div>

    <main class="sheet relative mx-auto my-6 max-w-[760px] overflow-hidden rounded-2xl border border-line bg-white p-8 shadow-card">
        @if ($payment->status === PaymentStatus::Reversed)
            <div class="pointer-events-none absolute inset-0 grid place-items-center" aria-hidden="true">
                <span class="-rotate-12 rounded-2xl border-4 border-danger px-6 py-2 text-5xl font-black tracking-widest text-danger/40 uppercase">Reversed</span>
            </div>
        @endif

        <header class="flex items-start justify-between gap-6 border-b-2 border-ink pb-4">
            <div>
                <h1 class="m-0 text-xl font-bold uppercase">{{ $school->name }}</h1>
                <p class="m-0 mt-1 text-sm text-muted">{{ $school->address }}{{ $school->phone ? ' · '.$school->phone : '' }}</p>
            </div>
            <div class="text-right">
                <div class="text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase">Official receipt</div>
                <div class="font-mono text-lg font-bold">{{ $payment->receipt_no }}</div>
                <div class="text-sm text-muted">{{ $payment->paid_at?->format('j F Y') }}</div>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-x-8 gap-y-3 py-5 text-[15px]">
            <div><div class="text-sm text-muted">Received from</div><div class="font-semibold">{{ $payment->guardian?->fullName() ?? 'Parent of '.$student->first_name }}</div></div>
            <div><div class="text-sm text-muted">For</div><div class="font-semibold">{{ $student->fullName() }} <span class="font-mono text-sm font-normal text-muted">({{ $student->admission_no }})</span></div></div>
            <div><div class="text-sm text-muted">Paid by</div><div class="font-semibold">{{ $payment->method->label() }}{{ $payment->reference && $payment->method !== \App\Enums\PaymentMethod::Paystack ? ' · '.$payment->reference : '' }}</div></div>
            <div><div class="text-sm text-muted">Amount</div><div class="text-2xl font-bold">{{ Money::format($payment->amount) }}</div></div>
            <div class="col-span-2 rounded-xl bg-canvas px-4 py-3"><span class="text-sm text-muted">The sum of</span> <span class="font-semibold">{{ Money::inWords($payment->amount) }}</span></div>
        </section>

        @if ($payment->allocations->isNotEmpty())
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-line text-xs font-semibold text-muted uppercase">
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

        <footer class="mt-8 flex items-end justify-between gap-6 text-sm text-muted">
            <span>{{ $payment->method === \App\Enums\PaymentMethod::Paystack ? 'Paid online and confirmed by Paystack.' : 'Received by '.($payment->recorder?->name ?? 'the bursary').'.' }}</span>
            <span class="w-48 border-t border-dashed border-line pt-1 text-center">Bursar’s signature &amp; stamp</span>
        </footer>
    </main>
</x-layouts.base>
