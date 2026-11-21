@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
@endphp

<x-layouts.app title="Payments">
    <x-page-header kicker="Finance" title="Payments" :description="$count.' '.str('payment')->plural($count).' confirmed between '.$from->format('j M').' and '.$to->format('j M Y').'.'" />

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-5" aria-label="Collections by method">
        <x-stat-card label="Collected" :value="Money::format($total)" icon="banknote-arrow-down" tone="naija" class="col-span-2 lg:col-span-1" />
        @foreach ($methods as $paymentMethod)
            @php($row = $byMethod[$paymentMethod->value] ?? null)
            <a href="{{ route('school.payments.index', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'method' => $method === $paymentMethod ? null : $paymentMethod->value])) }}" @class([
                'flex flex-col gap-1 rounded-2xl border bg-white px-4 py-3 transition hover:border-brand',
                'border-brand-600 ring-2 ring-brand-100' => $method === $paymentMethod,
                'border-line' => $method !== $paymentMethod,
            ])>
                <span class="text-sm text-muted">{{ $paymentMethod->label() }}</span>
                <span class="text-lg font-bold">{{ Money::format((int) ($row->total ?? 0)) }}</span>
                <span class="text-xs text-muted">{{ (int) ($row->payments ?? 0) }} payments</span>
            </a>
        @endforeach
    </section>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4" role="search">
            @if ($method)<input type="hidden" name="method" value="{{ $method->value }}">@endif
            <div class="flex w-40 flex-col gap-1.5">
                <label for="from" class="text-sm font-semibold">From</label>
                <input id="from" name="from" type="date" value="{{ $from->toDateString() }}" class="field">
            </div>
            <div class="flex w-40 flex-col gap-1.5">
                <label for="to" class="text-sm font-semibold">To</label>
                <input id="to" name="to" type="date" value="{{ $to->toDateString() }}" class="field">
            </div>
            <div class="flex min-w-56 flex-1 flex-col gap-1.5">
                <label for="q" class="text-sm font-semibold">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Student, receipt or reference" class="field">
            </div>
            <x-button variant="secondary" icon="search">Show</x-button>
        </form>

        @if ($payments->isEmpty())
            <x-empty-state icon="banknote-arrow-down" title="No payments in this period" body="Payments are recorded from a student’s invoice, or come in online through the parent portal." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Receipt</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Student</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Method</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Amount</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr class="border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3 text-sm whitespace-nowrap">{{ ($payment->paid_at ?? $payment->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-3 font-mono text-sm">{{ $payment->receipt_no ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('school.students.show', $payment->student).'#fees' }}" class="font-medium hover:text-brand-600">{{ $payment->student->registerName() }}</a>
                                    <span class="block text-xs text-muted">{{ $payment->method === \App\Enums\PaymentMethod::Paystack ? 'Paid online' : 'By '.($payment->recorder?->name ?? '—') }}</span>
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    {{ $payment->method->label() }}
                                    @if ($payment->reference)<span class="block max-w-48 truncate font-mono text-xs text-muted">{{ $payment->reference }}</span>@endif
                                </td>
                                <td class="px-3 py-3 text-right font-semibold">{{ Money::format($payment->amount) }}</td>
                                <td class="px-3 py-3"><x-badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-badge></td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1">
                                        @if (in_array($payment->status, [PaymentStatus::Confirmed, PaymentStatus::Reversed], true))
                                            <x-button variant="ghost" size="sm" icon="printer" :href="route('school.payments.receipt', $payment)">Receipt</x-button>
                                        @endif
                                        @can('payments.record')
                                            @if ($payment->status === PaymentStatus::Confirmed)
                                                <x-button variant="ghost" size="sm" icon="undo-2" type="button" x-data x-on:click="$dispatch('edit-reverse', { action: @js(route('school.payments.reverse', $payment)), values: { receipt: @js($payment->receipt_no), amount: @js(Money::format($payment->amount)) } })">Reverse</x-button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="border-t border-line px-5 py-3">{{ $payments->links() }}</div>
            @endif
        @endif
    </x-card>

    @can('payments.record')
        <div x-data="editPanel('reverse')">
            <x-drawer name="reverse" title="Reverse a payment" title-expression="'Reverse receipt ' + (values.receipt ?? '')" description="For a bounced cheque or a payment entered twice. The receipt stays on record, marked reversed, and the invoices it paid reopen.">
                <form method="POST" :action="action" class="flex flex-col gap-4" id="reverse-form">
                    @csrf
                    <p class="m-0 rounded-xl bg-canvas px-4 py-3 text-[15px]">Amount: <b x-text="values.amount"></b></p>
                    <x-field label="Reason" name="reason" bag="reverse" placeholder="e.g. Cheque returned unpaid" required />
                </form>
                <x-slot:footer><x-button variant="danger" icon="undo-2" form="reverse-form">Reverse payment</x-button></x-slot:footer>
            </x-drawer>
        </div>
    @endcan
</x-layouts.app>
