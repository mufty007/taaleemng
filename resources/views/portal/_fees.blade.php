{{-- A child's school fees in the parent and student portal. Expects $invoices, $payments, $balance and $canPayOnline. --}}
@php
    use App\Support\Money;
@endphp

<div class="flex flex-col gap-4">
    <div @class(['flex flex-wrap items-center gap-4 rounded-2xl border p-5', 'border-danger/30 bg-danger-50' => $balance > 0, 'border-line bg-white' => $balance <= 0])>
        <div class="min-w-0 flex-1">
            <div class="text-sm text-muted">{{ $balance > 0 ? 'Fees outstanding' : ($balance < 0 ? 'In credit' : 'School fees') }}</div>
            <div @class(['text-3xl font-bold tracking-[-0.02em]', 'text-danger' => $balance > 0, 'text-naija-700' => $balance <= 0])>{{ $balance === 0 ? 'All paid' : Money::format(abs($balance)) }}</div>
        </div>
        @if ($balance > 0 && ! $canPayOnline)
            <p class="m-0 max-w-sm text-sm text-muted">Pay at the school bursary or by bank transfer, then keep your receipt.</p>
        @endif


    </div>

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Invoices</h2>
        @forelse ($invoices as $invoice)
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ $invoice->term->name }} · {{ $invoice->term->academicSession->name }}</div>
                    <div class="text-sm text-muted">{{ Money::format($invoice->total) }}{{ $invoice->due_on ? ' · pay by '.$invoice->due_on->format('j M Y') : '' }}</div>
                </div>
                <x-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-badge>
                <x-button data-native variant="ghost" size="sm" icon="printer" :href="route('school.invoices.print', $invoice)">Invoice</x-button>
                @if ($invoice->balance() > 0 && $canPayOnline)
                    <form method="POST" action="{{ route('school.portal.invoices.pay', $invoice) }}" data-native>
                        @csrf
                        <x-button variant="brand" size="sm" icon="credit-card">Pay {{ Money::format($invoice->balance()) }} online</x-button>
                    </form>
                @elseif ($invoice->balance() > 0)
                    <span class="text-sm font-semibold text-danger">{{ Money::format($invoice->balance()) }} left</span>
                @endif
            </div>
        @empty
            <p class="m-0 px-5 py-4 text-sm text-muted">No invoices yet.</p>
        @endforelse
    </x-card>

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Receipts</h2>
        @forelse ($payments as $payment)
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ Money::format($payment->amount) }} <span class="text-sm font-normal text-muted">· {{ $payment->method->label() }}</span></div>
                    <div class="text-xs text-muted">{{ $payment->paid_at?->format('j M Y') }} · {{ $payment->receipt_no }}</div>
                </div>
                <x-button data-native variant="ghost" size="sm" icon="printer" :href="route('school.payments.receipt', $payment)">Receipt</x-button>
            </div>
        @empty
            <p class="m-0 px-5 py-4 text-sm text-muted">No payments yet.</p>
        @endforelse
    </x-card>
</div>
