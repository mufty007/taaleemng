<x-layouts.app title="Settings">
    <x-page-header kicker="System" title="Settings" description="Admissions, promotion, payments and how document numbers are built for {{ $school->name }}." />

    {{-- What the school pays Ta'aleem. --}}
    <x-card class="flex flex-wrap items-start gap-6">
        <div class="min-w-[220px] flex-1">
            <h2 class="m-0 text-lg font-semibold">Ta’aleem subscription</h2>
            <p class="m-0 mt-1 text-sm text-muted">
                {{ $school->plan->label() }} plan{{ $school->pricePerStudent() ? ' · '.\App\Support\Money::format($school->pricePerStudent()).' per student per term' : '' }}{{ $school->paid_until ? ' · paid up to '.$school->paid_until->format('j M Y') : '' }}.
                Questions about your plan? Email {{ config('taaleem.marketing.contact_email') }}.
            </p>
        </div>
        <ul class="m-0 flex w-full list-none flex-col gap-2 p-0 sm:w-auto sm:min-w-[320px]">
            @forelse ($subscriptionInvoices as $invoice)
                <li class="flex items-center justify-between gap-3 rounded-xl bg-canvas px-3 py-2 text-sm">
                    <span><span class="font-mono">{{ $invoice->number }}</span> · {{ $invoice->period }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold">{{ \App\Support\Money::format($invoice->amount) }}</span>
                        <x-badge :tone="$invoice->isOverdue() ? 'danger' : $invoice->status->tone()">{{ $invoice->isOverdue() ? 'Overdue' : ($invoice->status === \App\Enums\SchoolInvoiceStatus::Sent ? 'Due '.$invoice->due_on->format('j M') : 'Paid') }}</x-badge>
                    </span>
                </li>
            @empty
                <li class="text-sm text-muted">No invoices yet.</li>
            @endforelse
        </ul>
    </x-card>

    <form method="POST" action="{{ route('school.settings.update') }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Admissions & results</h2>

                <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas px-4 py-3">
                    <input type="checkbox" name="admissions_open" value="1" @checked(old('admissions_open', $admissionsOpen)) class="mt-1 size-4 rounded border-line">
                    <span>
                        <span class="block text-sm font-semibold">Online admission form is open</span>
                        <span class="block text-sm text-muted">Families can apply at your public admissions page.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas px-4 py-3">
                    <input type="checkbox" name="show_positions" value="1" @checked(old('show_positions', $showPositions)) class="mt-1 size-4 rounded border-line">
                    <span>
                        <span class="block text-sm font-semibold">Show class positions on report cards</span>
                        <span class="block text-sm text-muted">Turn off if you prefer not to rank students publicly.</span>
                    </span>
                </label>

                <x-field
                    label="Promotion pass mark (%)"
                    name="promotion_pass_mark"
                    type="number"
                    min="0"
                    max="100"
                    step="0.5"
                    :value="old('promotion_pass_mark', $promotionPassMark)"
                    hint="Students within 5 marks below this can be promoted on probation."
                    required
                />
            </x-card>

            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Online fee payouts</h2>
                <p class="m-0 text-sm text-muted">Parents pay through Ta’aleem’s Paystack. Settlements go straight to this school’s bank account — you do not need your own Paystack keys.</p>
                @unless ($platformPaystackReady)
                    <div class="rounded-2xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold">Platform Paystack is not configured yet. Bank details can be saved once support turns it on.</div>
                @endunless
                @if ($paystackReady)
                    <div class="rounded-2xl border border-naija/20 bg-naija/5 px-4 py-3 text-sm text-naija-700">Online payouts are connected for this school.</div>
                @endif
                <x-select-field label="Bank" name="payout_bank_code" :options="$banks" :value="old('payout_bank_code', $payoutBankCode)" placeholder="Choose bank" />
                <x-field label="Account number" name="payout_account_number" :value="old('payout_account_number', $payoutAccountNumber)" placeholder="0123456789" inputmode="numeric" maxlength="10" hint="10-digit NUBAN." />
                <x-field label="Account name" name="payout_account_name" :value="old('payout_account_name', $payoutAccountName)" placeholder="Greenfield Academy" />
                <p class="m-0 text-sm text-muted">School profile details (name, logo, address) are under <a href="{{ route('school.profile.edit') }}" class="font-semibold text-brand-600 hover:text-ink">School information</a>.</p>
            </x-card>
        </div>

        <x-card class="flex flex-col gap-4">
            <div>
                <h2 class="m-0 text-lg font-semibold">Document numbers</h2>
                <p class="m-0 mt-1 text-sm text-muted">Tokens: <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{SCHOOL}</code> <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{YYYY}</code> <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{YY}</code> <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{SEQ:4}</code>. Changing a pattern does not reset the counter.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="py-2 pr-3">Document</th>
                            <th scope="col" class="py-2 pr-3">Pattern</th>
                            <th scope="col" class="py-2">Next number</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($numbering as $row)
                            <tr class="border-t border-line align-top">
                                <td class="py-3 pr-3">
                                    <span class="font-semibold">{{ $row['label'] }}</span>
                                    <span class="mt-0.5 block font-mono text-xs text-muted">{{ $row['key'] }} · next #{{ $row['next_value'] }}</span>
                                </td>
                                <td class="py-3 pr-3">
                                    <input
                                        type="text"
                                        name="numbering[{{ $row['key'] }}]"
                                        value="{{ old('numbering.'.$row['key'], $row['pattern']) }}"
                                        class="field font-mono text-sm @error('numbering.'.$row['key']) field-error @enderror"
                                        required
                                    >
                                    @error('numbering.'.$row['key'])
                                        <p class="m-0 mt-1 text-[13px] font-medium text-danger">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-3 font-mono text-sm text-muted">{{ $row['preview'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div>
            <x-button variant="brand" size="lg" icon="check">Save settings</x-button>
        </div>
    </form>
</x-layouts.app>
