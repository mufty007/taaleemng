@php
    use App\Support\Money;

    $price = $school->pricePerStudent();
@endphp

<div class="flex flex-col gap-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="m-0 text-base font-semibold">Plan</h2>
            <p class="m-0 mt-1 text-sm text-muted">{{ $price ? Money::format($price).' per student per term · about '.Money::format($price * (int) $school->students_count).' a term for '.number_format((int) $school->students_count).' students.' : 'No price set yet: set one here or in the plan prices (.env).' }}</p>
            <form method="POST" action="{{ route('platform.schools.plan', $school) }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-select-field label="Plan" name="plan" bag="plan" :value="$school->plan->value" :options="$plans" />
                <x-select-field label="Billing" name="billing_cycle" bag="plan" :value="$school->billing_cycle" :options="['termly' => 'Every term', 'annual' => 'Once a year']" />
                <x-field label="Price per student (₦)" name="price_per_student" bag="plan" inputmode="decimal" :value="old('price_per_student', $school->price_per_student ? Money::toNaira($school->price_per_student) : '')" :placeholder="$school->plan->defaultPrice() ? 'Plan price: '.Money::toNaira($school->plan->defaultPrice()) : 'e.g. 1,500'" />
                <x-field label="Paid up to" name="paid_until" type="date" bag="plan" :value="old('paid_until', $school->paid_until?->format('Y-m-d'))" />
                <div class="sm:col-span-2"><x-field label="Notes (only super admins see these)" name="billing_notes" bag="plan" :value="old('billing_notes', $school->billing_notes)" placeholder="e.g. 10% discount for the first year" /></div>
                <label class="flex items-start gap-2 text-sm sm:col-span-2">
                    <input type="hidden" name="apply_modules" value="0">
                    <input type="checkbox" name="apply_modules" value="1" class="mt-0.5 size-4 rounded border-line text-brand-600">
                    <span>Also switch modules to this plan’s defaults</span>
                </label>
                <div class="sm:col-span-2"><x-button variant="brand" icon="check">Save plan</x-button></div>
            </form>
        </x-card>

        <x-card>
            <h2 class="m-0 text-base font-semibold">New invoice</h2>
            <p class="m-0 mt-1 text-sm text-muted">Bills {{ number_format((int) $school->students_count) }} active students{{ $price ? ' × '.Money::format($price).' = '.Money::format($price * (int) $school->students_count) : '' }}. It starts as a draft; send it when it’s right.</p>
            <form method="POST" action="{{ route('platform.schools.invoices.store', $school) }}" class="mt-4 flex flex-col gap-3">
                @csrf
                <x-field label="Period" name="period" bag="invoice" :value="old('period', $currentTerm ? $currentTerm->name.' '.$currentTerm->academicSession->name : '')" placeholder="First Term 2026/2027" required />
                <x-field label="Due on" name="due_on" type="date" bag="invoice" :value="old('due_on', now()->addDays(14)->format('Y-m-d'))" required />
                <x-field label="Note on the invoice" name="notes" bag="invoice" placeholder="Optional" />
                <x-button variant="secondary" icon="file-plus" class="self-start" :disabled="! $price">Create invoice</x-button>
            </form>
        </x-card>
    </div>

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-4 text-base font-semibold">Invoices</h2>
        @forelse ($invoices as $invoice)
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0" x-data="{ paying: false }">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-semibold">{{ $invoice->number }}</span>
                        <x-badge :tone="$invoice->isOverdue() ? 'danger' : $invoice->status->tone()">{{ $invoice->isOverdue() ? 'Overdue' : $invoice->status->label() }}</x-badge>
                    </div>
                    <div class="text-sm text-muted">{{ $invoice->period }} · {{ $invoice->students }} × {{ Money::format($invoice->unit_price) }} · {{ $invoice->status === \App\Enums\SchoolInvoiceStatus::Paid ? 'paid '.$invoice->paid_at?->format('j M Y').' ('.$invoice->payment_method.')' : 'due '.$invoice->due_on->format('j M Y') }}</div>
                </div>
                <span class="font-bold tabular-nums">{{ Money::format($invoice->amount) }}</span>
                <div class="flex flex-wrap gap-1.5">
                    <x-button variant="ghost" size="sm" icon="printer" :href="route('platform.school-invoices.print', $invoice)" target="_blank" data-native>Print</x-button>
                    @if ($invoice->status->isOpen())
                        <form method="POST" action="{{ route('platform.school-invoices.send', $invoice) }}">@csrf<x-button variant="soft" size="sm" icon="send">{{ $invoice->status === \App\Enums\SchoolInvoiceStatus::Sent ? 'Send again' : 'Send' }}</x-button></form>
                        <x-button type="button" variant="soft" size="sm" icon="check" x-on:click="paying = ! paying">Paid</x-button>
                        <form method="POST" action="{{ route('platform.school-invoices.void', $invoice) }}" data-confirm="Void {{ $invoice->number }}? It stays on record but no longer counts as owed." data-confirm-title="Void invoice" data-confirm-action="Void">@csrf<x-button variant="ghost" size="sm">Void</x-button></form>
                    @endif
                </div>
                @if ($invoice->status->isOpen())
                    <form method="POST" action="{{ route('platform.school-invoices.paid', $invoice) }}" x-show="paying" x-collapse x-cloak class="grid w-full grid-cols-1 gap-3 rounded-xl bg-canvas p-3 sm:grid-cols-4">
                        @csrf
                        <x-select-field label="How" name="payment_method" :id="'method-'.$invoice->ulid" :options="['Bank transfer' => 'Bank transfer', 'Cash' => 'Cash', 'Cheque' => 'Cheque', 'Paystack' => 'Paystack', 'Other' => 'Other']" />
                        <x-field label="Reference" name="payment_reference" :id="'ref-'.$invoice->ulid" />
                        <x-field label="Paid on" name="paid_on" type="date" :id="'date-'.$invoice->ulid" :value="now()->format('Y-m-d')" />
                        <x-button variant="brand" class="self-end">Record payment</x-button>
                    </form>
                @endif
            </div>
        @empty
            <x-empty-state icon="receipt" title="No invoices yet" body="Create the first one above when the school starts paying." />
        @endforelse
    </x-card>
</div>
