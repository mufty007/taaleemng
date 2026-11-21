{{-- The student's fees: balance, invoices and payments. Expects $student and $fees (invoices, payments, balance, methods). --}}
@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
    $balance = $fees['balance'];
@endphp

<div class="flex flex-col gap-5">
    @if (session('receipt'))
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-naija/20 bg-naija-50 px-5 py-3 text-[15px] text-naija-700">
            <span>Payment recorded.</span>
            <x-button variant="secondary" size="sm" icon="printer" :href="session('receipt')">Print receipt</x-button>
        </div>
    @endif

    <x-card>
        <div class="flex flex-wrap items-center gap-4">
            <div class="min-w-0 flex-1">
                <div class="text-sm text-muted">{{ $balance < 0 ? 'In credit' : 'Owes the school' }}</div>
                <div @class(['text-3xl font-bold tracking-[-0.02em]', 'text-danger' => $balance > 0, 'text-naija-700' => $balance <= 0])>{{ Money::format(abs($balance)) }}</div>
            </div>
            @can('payments.record')
                <x-button variant="brand" icon="banknote-arrow-down" type="button" x-data x-on:click="$dispatch('open-drawer', 'record-payment')">Record payment</x-button>
            @endcan
        </div>
    </x-card>

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Invoices</h2>
        @forelse ($fees['invoices'] as $invoice)
            <a href="{{ route('school.invoices.show', $invoice) }}" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ $invoice->term->name }} · {{ $invoice->term->academicSession->name }}</div>
                    <div class="font-mono text-xs text-muted">{{ $invoice->invoice_no }}</div>
                </div>
                <div class="text-right text-sm">
                    <div class="font-semibold">{{ Money::format($invoice->total) }}</div>
                    @if ($invoice->balance() > 0)<div class="text-danger">{{ Money::format($invoice->balance()) }} left</div>@endif
                </div>
                <x-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-badge>
            </a>
        @empty
            <p class="m-0 px-5 py-4 text-sm text-muted">No invoices yet.</p>
        @endforelse
    </x-card>

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Payments</h2>
        @forelse ($fees['payments'] as $payment)
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ Money::format($payment->amount) }} <span class="text-sm font-normal text-muted">· {{ $payment->method->label() }}</span></div>
                    <div class="text-xs text-muted">{{ ($payment->paid_at ?? $payment->created_at)->format('j M Y') }}{{ $payment->receipt_no ? ' · '.$payment->receipt_no : '' }}</div>
                </div>
                <x-badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-badge>
                @if (in_array($payment->status, [PaymentStatus::Confirmed, PaymentStatus::Reversed], true))
                    <a data-native href="{{ route('school.payments.receipt', $payment) }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Receipt</a>
                @endif
            </div>
        @empty
            <p class="m-0 px-5 py-4 text-sm text-muted">No payments yet.</p>
        @endforelse
    </x-card>
</div>

