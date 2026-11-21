@php
    use App\Enums\InvoiceStatus;
    use App\Enums\PaymentStatus;
    use App\Support\Money;
    $student = $invoice->student;
    $guardian = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
@endphp

<x-layouts.app :title="'Invoice '.$invoice->invoice_no">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.invoices.index', ['term' => $invoice->term_id]) }}" class="hover:text-ink">Invoices</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="font-mono text-ink">{{ $invoice->invoice_no }}</span>
    </div>

    <x-page-header :kicker="$invoice->term->name.' · '.$invoice->term->academicSession->name" :title="$student->fullName()" :description="$invoice->enrolment->classArm->displayName().' · '.$student->admission_no.($guardian ? ' · '.$guardian->fullName().', '.$guardian->phone : '')">
        <x-slot:actions>
            <x-badge :tone="$invoice->status->tone()" class="px-3 py-1.5 text-sm">{{ $invoice->status->label() }}</x-badge>
            <x-button data-native variant="secondary" icon="printer" :href="route('school.invoices.print', $invoice)">Print invoice</x-button>
            @can('payments.record')
                @if ($invoice->status->isOpen())
                    <x-button variant="brand" icon="banknote-arrow-down" type="button" x-data x-on:click="$dispatch('open-drawer', 'record-payment')">Record payment</x-button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('receipt'))
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-naija/20 bg-naija-50 px-5 py-3 text-[15px] text-naija-700">
            <span>Payment recorded.</span>
            <x-button variant="secondary" size="sm" icon="printer" :href="session('receipt')">Print receipt</x-button>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
        <x-card :padding="false">
            <table class="w-full border-collapse text-left text-[15px]">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3 font-semibold">Item</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lines as $line)
                        <tr class="group border-t border-line">
                            <td class="px-5 py-3">
                                <span class="flex items-center gap-2">
                                    {{ $line->description }}
                                    @can('fees.manage')
                                        @unless ($invoice->status === InvoiceStatus::Void)
                                            <span class="ml-auto flex gap-0.5 opacity-60 transition group-hover:opacity-100">
                                                <button type="button" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Edit {{ $line->description }}" x-data x-on:click="$dispatch('edit-line', { action: @js(route('school.invoices.lines.update', [$invoice, $line])), values: @js(['description' => $line->description, 'amount' => \App\Support\Money::toNaira($line->amount), 'discount' => $line->amount < 0]) })"><x-lucide-pencil class="size-3.5" aria-hidden="true" /></button>
                                                @if ($invoice->lines->count() > 1)
                                                    <form method="POST" action="{{ route('school.invoices.lines.destroy', [$invoice, $line]) }}" x-data x-on:submit="if (! confirm(@js('Remove '.$line->description.' from this invoice?'))) $event.preventDefault()">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger" aria-label="Remove {{ $line->description }}"><x-lucide-trash-2 class="size-3.5" aria-hidden="true" /></button>
                                                    </form>
                                                @endif
                                            </span>
                                        @endunless
                                    @endcan
                                </span>
                            </td>
                            <td @class(['px-5 py-3 text-right font-mono', 'text-naija-700' => $line->amount < 0])>{{ Money::format($line->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-ink">
                        <th scope="row" class="px-5 py-3 text-left">Total</th>
                        <td class="px-5 py-3 text-right font-mono font-bold">{{ Money::format($invoice->total) }}</td>
                    </tr>
                    <tr class="border-t border-line">
                        <th scope="row" class="px-5 py-2 text-left font-normal text-muted">Paid</th>
                        <td class="px-5 py-2 text-right font-mono text-naija-700">{{ Money::format($invoice->amount_paid) }}</td>
                    </tr>
                    <tr class="border-t border-line">
                        <th scope="row" class="px-5 py-3 text-left">Balance</th>
                        <td class="px-5 py-3 text-right font-mono text-lg font-bold">{{ Money::format($invoice->balance()) }}</td>
                    </tr>
                </tfoot>
            </table>
            @can('fees.manage')
                @unless ($invoice->status === InvoiceStatus::Void)
                    <div class="flex flex-wrap gap-2 border-t border-line px-5 py-3">
                        <x-button variant="ghost" size="sm" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'add-line')">Add a charge or discount</x-button>
                        <x-button variant="ghost" size="sm" icon="calendar" type="button" x-data x-on:click="$dispatch('open-drawer', 'edit-invoice')">Pay-by date &amp; note</x-button>
                        <x-button variant="ghost" size="sm" icon="ban" type="button" x-data x-on:click="$dispatch('open-drawer', 'void')">Cancel invoice</x-button>
                    </div>
                @endunless
            @endcan
        </x-card>

        <div class="flex flex-col gap-5">
            <x-card>
                <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-[15px]">
                    <dt class="text-muted">Invoice</dt><dd class="m-0 text-right font-mono">{{ $invoice->invoice_no }}</dd>
                    <dt class="text-muted">Issued</dt><dd class="m-0 text-right">{{ $invoice->issued_at->format('j M Y') }}</dd>
                    <dt class="text-muted">Pay by</dt><dd class="m-0 text-right">{{ $invoice->due_on?->format('j M Y') ?? '—' }}</dd>
                    <dt class="text-muted">All fees owed</dt><dd @class(['m-0 text-right font-semibold', 'text-naija-700' => $studentBalance < 0])>{{ $studentBalance < 0 ? Money::format(-$studentBalance).' credit' : Money::format($studentBalance) }}</dd>
                </dl>
                @if ($invoice->notes)
                    <p class="m-0 mt-3 border-t border-line pt-3 text-sm whitespace-pre-line text-muted">{{ $invoice->notes }}</p>
                @endif
                <x-button variant="soft" size="sm" icon="arrow-right" class="mt-4" :href="route('school.students.show', $student).'#fees'">Student’s fees</x-button>
            </x-card>

            <x-card :padding="false">
                <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Payments on this invoice</h2>
                @forelse ($invoice->allocations as $allocation)
                    @php($payment = $allocation->payment)
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                        <div class="min-w-0">
                            <div class="font-medium">{{ Money::format($allocation->amount) }} <span class="text-sm font-normal text-muted">· {{ $payment->method->label() }}</span></div>
                            <div class="text-xs text-muted">{{ $payment->paid_at?->format('j M Y') }} · {{ $payment->receipt_no }}</div>
                        </div>
                        @if ($payment->status === PaymentStatus::Confirmed)
                            <a data-native href="{{ route('school.payments.receipt', $payment) }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Receipt</a>
                        @endif
                    </div>
                @empty
                    <p class="m-0 px-5 py-4 text-sm text-muted">Nothing paid yet.</p>
                @endforelse
            </x-card>
        </div>
    </div>

    @can('payments.record')
        @include('school.payments._record-drawer', ['suggestedAmount' => $invoice->balance()])
    @endcan

    @can('fees.manage')
        <div x-data="editPanel('line')">
            <x-drawer name="line" title="Edit invoice line" description="Changing an amount updates the total and the balance straight away.">
                <form method="POST" :action="action" class="flex flex-col gap-4">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-line-description" class="text-sm font-semibold">Description</label>
                        <input id="edit-line-description" name="description" class="field" x-model="values.description" required maxlength="120">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-line-amount" class="text-sm font-semibold" x-text="values.discount ? 'Discount (₦)' : 'Amount (₦)'"></label>
                        <input id="edit-line-amount" name="amount" class="field font-mono" inputmode="decimal" :value="values.discount ? '-' + String(values.amount).replace('-', '') : values.amount" required>
                        <p class="m-0 text-xs text-muted">A discount keeps its minus sign.</p>
                    </div>
                    <x-button variant="brand" class="self-start" icon="check">Save line</x-button>
                </form>
                @error('amount', 'editLine')<p class="m-0 mt-2 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
            </x-drawer>
        </div>

        <x-drawer name="edit-invoice" title="Pay-by date and note" :show="$errors->editInvoice->isNotEmpty()">
            <form method="POST" action="{{ route('school.invoices.update', $invoice) }}" class="flex flex-col gap-4">
                @csrf
                @method('PATCH')
                <x-field label="Pay by" name="due_on" type="date" :value="$invoice->due_on?->toDateString()" bag="editInvoice" />
                <div class="flex flex-col gap-1.5">
                    <label for="invoice-notes" class="text-sm font-semibold">Note on the invoice</label>
                    <textarea id="invoice-notes" name="notes" rows="4" maxlength="500" class="field resize-y" placeholder="e.g. Pay into GTBank 0123456789, Greenfield Academy">{{ old('notes', $invoice->notes) }}</textarea>
                </div>
                <x-button variant="brand" class="self-start" icon="check">Save</x-button>
            </form>
        </x-drawer>

        <x-drawer name="add-line" title="Add a charge or discount" description="For this invoice only. Use it for optional items (bus, uniform) or a sibling discount." :show="$errors->addLine->isNotEmpty()">
            <form method="POST" action="{{ route('school.invoices.lines.store', $invoice) }}" class="flex flex-col gap-4" id="add-line-form">
                @csrf
                <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Charge or discount">
                    @foreach (['charge' => 'Extra charge', 'discount' => 'Discount'] as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3.5 py-2.5 text-[15px] has-checked:border-brand-600 has-checked:bg-brand-50">
                            <input type="radio" name="kind" value="{{ $value }}" class="accent-brand-600" @checked(old('kind', 'charge') === $value)> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <x-field label="Description" name="description" bag="addLine" placeholder="e.g. School bus, Sibling discount" required />
                <x-field label="Amount (₦)" name="amount" bag="addLine" inputmode="decimal" placeholder="15,000" required />
            </form>
            <x-slot:footer><x-button variant="brand" form="add-line-form">Add to invoice</x-button></x-slot:footer>
        </x-drawer>

        <x-drawer name="void" title="Cancel this invoice" description="Use this for an invoice issued by mistake. Anything paid on it becomes credit for the student." :show="$errors->void->isNotEmpty()">
            <form method="POST" action="{{ route('school.invoices.void', $invoice) }}" class="flex flex-col gap-4" id="void-form">
                @csrf
                <x-field label="Reason" name="reason" bag="void" placeholder="e.g. Student withdrew before resumption" required />
            </form>
            <x-slot:footer><x-button variant="danger" icon="ban" form="void-form">Cancel invoice</x-button></x-slot:footer>
        </x-drawer>
    @endcan
</x-layouts.app>
