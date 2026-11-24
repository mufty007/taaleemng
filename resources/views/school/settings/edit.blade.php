@php
    $settingsTab = match (true) {
        $errors->has('class_structure') || $errors->has('merge_arms') => 'classes',
        $errors->hasAny(['payout_bank_code', 'payout_account_number', 'payout_account_name']) => 'payouts',
        collect($errors->keys())->contains(fn ($key) => str_starts_with((string) $key, 'numbering')) => 'numbering',
        collect($errors->keys())->contains(fn ($key) => str_starts_with((string) $key, 'setup')) => 'setup',
        collect($errors->keys())->contains(fn ($key) => str_starts_with((string) $key, 'role_onboarding')) => 'onboarding',
        $errors->hasAny(['admissions_open', 'show_positions', 'promotion_pass_mark']) => 'admissions',
        default => 'subscription',
    };
@endphp

<x-layouts.app title="Settings">
    <x-page-header kicker="System" title="Settings" description="Admissions, promotion, payments and how document numbers are built for {{ $school->name }}." />

    {{-- Subscription forms sit outside the settings form; their buttons point at them with form="…".
         data-native: Paystack checkout is a full-page redirect, not an in-app page swap. --}}
    <form id="subscription-checkout" method="POST" action="{{ route('school.settings.subscription.checkout') }}" class="hidden" data-native>@csrf</form>
    @foreach ($subscriptionInvoices as $invoice)
        @if ($invoice->status === \App\Enums\SchoolInvoiceStatus::Sent && $platformPaystackReady)
            <form id="pay-subscription-{{ $invoice->id }}" method="POST" action="{{ route('school.settings.subscription.pay', $invoice) }}" class="hidden" data-native>@csrf</form>
        @endif
    @endforeach

    <form
        method="POST"
        action="{{ route('school.settings.update') }}"
        class="flex flex-col gap-5"
        x-data
        x-on:submit="$refs.settingsTab.value = (window.location.hash || '#admissions').slice(1)"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="_tab" value="{{ $settingsTab }}" x-ref="settingsTab">

        <x-tabs
            :tabs="[
                'subscription' => 'Subscription',
                'admissions' => 'Admissions',
                'payouts' => 'Payouts',
                'numbering' => 'Numbers',
                'classes' => 'Classes',
                'setup' => 'Checklist',
                'onboarding' => 'Onboarding',
            ]"
            :default="$settingsTab"
            label="Settings sections"
        >
            <x-tab name="subscription">
                @php
                    $meters = [
                        ['label' => 'Students', 'used' => $seats['students'], 'max' => $seats['max_students']],
                        ['label' => 'Staff', 'used' => $seats['staff'], 'max' => $seats['max_staff']],
                    ];
                    [$standingTone, $standing] = match (true) {
                        $school->status === \App\Enums\SchoolStatus::Trial && $school->trial_ends_at => ['gold', 'Free trial until '.$school->trial_ends_at->format('j M Y')],
                        $school->paid_until?->isFuture() => ['naija', 'Paid up to '.$school->paid_until->format('j M Y')],
                        $school->paid_until !== null => ['danger', 'Renewal was due '.$school->paid_until->format('j M Y')],
                        default => ['neutral', ucfirst($school->status->label())],
                    };
                @endphp
                <div class="flex flex-col gap-5">
                    {{-- Where the school stands today. --}}
                    <x-card class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <div class="min-w-0">
                            <p class="kicker m-0">Ta’aleem subscription</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <h2 class="m-0 text-2xl font-semibold tracking-tight">{{ $school->plan->label() }}</h2>
                                <x-badge :tone="$standingTone">{{ $standing }}</x-badge>
                            </div>
                            <p class="m-0 mt-2 text-sm text-muted">
                                {{ $school->billing_model->label() }}{{ $school->billing_model === \App\Enums\BillingModel::Flat ? ', paid '.($school->billing_cycle === 'annual' ? 'yearly' : 'each term') : ', billed each term' }}.
                                Every plan includes every feature — only the number of students and staff changes.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($meters as $meter)
                                @php
                                    $percent = $meter['max'] ? min(100, (int) round($meter['used'] / max(1, $meter['max']) * 100)) : 0;
                                    $bar = $percent >= 100 ? 'bg-danger' : ($percent >= 85 ? 'bg-gold' : 'bg-brand-600');
                                @endphp
                                <div class="rounded-2xl border border-line bg-canvas p-4">
                                    <p class="m-0 text-sm text-muted">{{ $meter['label'] }}</p>
                                    <p class="m-0 mt-1 text-xl font-semibold tabular-nums">
                                        {{ number_format($meter['used']) }}<span class="text-sm font-normal text-muted"> / {{ $meter['max'] ? number_format($meter['max']) : 'no limit' }}</span>
                                    </p>
                                    @if ($meter['max'])
                                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-line" role="meter" aria-label="{{ $meter['label'] }} seats used" aria-valuemin="0" aria-valuemax="{{ $meter['max'] }}" aria-valuenow="{{ $meter['used'] }}">
                                            <div class="h-full rounded-full {{ $bar }}" style="width: {{ $percent }}%"></div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-card>

                    {{-- Choose (or renew) a plan and pay with Paystack. --}}
                    <x-card x-data="{ billing: @js($school->billing_model === \App\Enums\BillingModel::PerStudent ? 'per_student' : ($school->billing_cycle === 'annual' ? 'flat-annual' : 'flat-termly')) }">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h2 class="m-0 text-lg font-semibold">Choose a plan</h2>
                                <p class="m-0 mt-1 text-sm text-muted">Pay securely with card, bank transfer or USSD through Paystack. Your plan switches as soon as the payment clears.</p>
                            </div>
                            <div class="flex flex-wrap gap-1 rounded-xl bg-chip p-1 text-sm font-medium" role="radiogroup" aria-label="How you pay">
                                @foreach (['flat-termly' => 'Per term', 'flat-annual' => 'Per year', 'per_student' => 'Per student'] as $value => $label)
                                    <label class="cursor-pointer rounded-lg px-3 py-1.5 transition" :class="billing === '{{ $value }}' ? 'bg-white text-ink shadow-card' : 'text-muted hover:text-ink'">
                                        <input type="radio" class="sr-only" value="{{ $value }}" x-model="billing">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <input type="hidden" name="billing_model" form="subscription-checkout" :value="billing === 'per_student' ? 'per_student' : 'flat'">
                        <input type="hidden" name="billing_cycle" form="subscription-checkout" :value="billing === 'flat-annual' ? 'annual' : 'termly'">

                        @unless ($platformPaystackReady)
                            <p class="m-0 mt-4 rounded-xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold">Online payment isn’t switched on yet. Email {{ config('taaleem.marketing.contact_email') }} and we’ll set up your plan.</p>
                        @endunless

                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            @foreach ($plans as $plan)
                                @php
                                    $tier = \App\Enums\Plan::from($plan['key']);
                                    $isCurrent = $school->plan === $tier;
                                    $tooSmall = ($plan['max_students'] !== null && $seats['students'] > $plan['max_students'])
                                        || ($plan['max_staff'] !== null && $seats['staff'] > $plan['max_staff']);
                                    $perStudentTotal = $tier->defaultPrice() && $seats['students'] > 0 ? \App\Support\Money::format($tier->defaultPrice() * $seats['students']) : null;
                                @endphp
                                <div @class([
                                    'flex flex-col gap-3 rounded-2xl border p-5',
                                    'border-brand-600 ring-1 ring-brand-600' => $isCurrent,
                                    'border-line' => ! $isCurrent,
                                ])>
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="m-0 text-base font-semibold">{{ $plan['name'] }}</h3>
                                        @if ($isCurrent)
                                            <x-badge tone="brand">Current</x-badge>
                                        @elseif ($plan['popular'])
                                            <x-badge tone="gold">Popular</x-badge>
                                        @endif
                                    </div>
                                    <div class="min-h-[3.25rem]">
                                        <p class="m-0 text-2xl font-bold tracking-tight" x-show="billing === 'flat-termly'">{{ $plan['flat_termly'] ?? '—' }}<span class="text-sm font-normal text-muted"> / term</span></p>
                                        <p class="m-0 text-2xl font-bold tracking-tight" x-show="billing === 'flat-annual'" x-cloak>{{ $plan['flat_annual'] ?? '—' }}<span class="text-sm font-normal text-muted"> / year</span></p>
                                        <div x-show="billing === 'per_student'" x-cloak>
                                            <p class="m-0 text-2xl font-bold tracking-tight">{{ $plan['per_student'] ?? '—' }}<span class="text-sm font-normal text-muted"> / student / term</span></p>
                                            @if ($perStudentTotal)
                                                <p class="m-0 text-xs text-muted">{{ $perStudentTotal }} for your {{ number_format($seats['students']) }} students</p>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="m-0 text-sm font-medium">Up to {{ number_format($plan['max_students']) }} students · {{ number_format($plan['max_staff']) }} staff</p>
                                    <p class="m-0 flex-1 text-sm text-muted">{{ $plan['description'] }}</p>
                                    @if ($tooSmall)
                                        <p class="m-0 rounded-xl bg-canvas px-3 py-2.5 text-center text-sm text-muted">Too small for your school today</p>
                                    @else
                                        <x-button
                                            type="submit"
                                            form="subscription-checkout"
                                            name="plan"
                                            value="{{ $plan['key'] }}"
                                            :variant="$isCurrent ? 'brand' : 'secondary'"
                                            :disabled="! $platformPaystackReady"
                                        >{{ $isCurrent ? 'Pay for '.$plan['name'] : 'Switch to '.$plan['name'] }}</x-button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="m-0 mt-4 text-sm text-muted">More than {{ number_format(collect($plans)->max('max_students')) }} students, or several campuses? Email {{ config('taaleem.marketing.contact_email') }} for a custom plan.</p>
                    </x-card>

                    <x-card>
                        <h2 class="m-0 text-lg font-semibold">Invoices from Ta’aleem</h2>
                        <ul class="m-0 mt-3 flex list-none flex-col gap-2 p-0">
                            @forelse ($subscriptionInvoices as $invoice)
                                <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-canvas px-3 py-2 text-sm">
                                    <span><span class="font-mono">{{ $invoice->number }}</span> · {{ $invoice->period }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="font-semibold">{{ \App\Support\Money::format($invoice->amount) }}</span>
                                        <x-badge :tone="$invoice->isOverdue() ? 'danger' : $invoice->status->tone()">{{ $invoice->isOverdue() ? 'Overdue' : ($invoice->status === \App\Enums\SchoolInvoiceStatus::Sent ? 'Due '.$invoice->due_on->format('j M') : 'Paid') }}</x-badge>
                                        @if ($invoice->status === \App\Enums\SchoolInvoiceStatus::Sent && $platformPaystackReady)
                                            <x-button type="submit" form="pay-subscription-{{ $invoice->id }}" variant="brand" size="sm">Pay online</x-button>
                                        @endif
                                    </span>
                                </li>
                            @empty
                                <li class="text-sm text-muted">No invoices yet.</li>
                            @endforelse
                        </ul>
                    </x-card>
                </div>
            </x-tab>

            <x-tab name="admissions">
                <x-card class="flex max-w-2xl flex-col gap-4">
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
            </x-tab>

            <x-tab name="payouts">
                <x-card class="flex max-w-2xl flex-col gap-4">
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
            </x-tab>

            <x-tab name="numbering">
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
            </x-tab>

            <x-tab name="classes">
                <x-card class="flex max-w-2xl flex-col gap-4">
                    <div>
                        <h2 class="m-0 text-lg font-semibold">Class structure</h2>
                        <p class="m-0 mt-1 text-sm text-muted">Some schools run JSS 1A and JSS 1B. Others keep one class per level. Switching to single classes can merge existing arms.</p>
                    </div>
                    <x-select-field label="How classes are organised" name="class_structure" :options="$classStructures" :value="old('class_structure', $school->class_structure->value)" />
                    @error('class_structure')
                        <p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>
                    @enderror
                    <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas px-4 py-3">
                        <input type="checkbox" name="merge_arms" value="1" @checked(old('merge_arms')) class="mt-1 size-4 rounded border-line">
                        <span>
                            <span class="block text-sm font-semibold">Merge arms into one class per level</span>
                            <span class="block text-sm text-muted">Required when switching from A/B/C to single classes. Students and subjects move into one class; empty arms are removed.</span>
                        </span>
                    </label>
                </x-card>
            </x-tab>

            <x-tab name="setup">
                <x-card class="flex max-w-2xl flex-col gap-4">
                    <div>
                        <h2 class="m-0 text-lg font-semibold">Setup checklist</h2>
                        <p class="m-0 mt-1 text-sm text-muted">Shown on the dashboard until each step is done. Turn steps off or rename them for your school.</p>
                    </div>
                    @foreach ($setupDefaults as $key => $default)
                        @php($custom = $school->setup_checklist[$key] ?? [])
                        <div class="rounded-xl border border-line p-3">
                            <label class="flex items-start gap-3">
                                <input type="hidden" name="setup[{{ $key }}][enabled]" value="0">
                                <input type="checkbox" name="setup[{{ $key }}][enabled]" value="1" @checked(old('setup.'.$key.'.enabled', $custom['enabled'] ?? true)) class="mt-1 size-4 rounded border-line">
                                <span class="min-w-0 flex-1">
                                    <input type="text" name="setup[{{ $key }}][title]" value="{{ old('setup.'.$key.'.title', $custom['title'] ?? $default['title']) }}" class="field mb-2 text-sm font-semibold">
                                    <input type="text" name="setup[{{ $key }}][body]" value="{{ old('setup.'.$key.'.body', $custom['body'] ?? $default['body']) }}" class="field text-sm">
                                </span>
                            </label>
                        </div>
                    @endforeach
                </x-card>
            </x-tab>

            <x-tab name="onboarding">
                <x-card class="flex flex-col gap-4">
                    <div>
                        <h2 class="m-0 text-lg font-semibold">Role onboarding</h2>
                        <p class="m-0 mt-1 text-sm text-muted">First-login tips for each role. One tip per line. Turn a role off to skip the welcome tour.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach ($roleDefaults as $key => $default)
                            @php($custom = $school->role_onboarding[$key] ?? [])
                            <div class="rounded-xl border border-line p-4">
                                <label class="mb-3 flex items-center gap-2 text-sm font-semibold">
                                    <input type="hidden" name="role_onboarding[{{ $key }}][enabled]" value="0">
                                    <input type="checkbox" name="role_onboarding[{{ $key }}][enabled]" value="1" @checked(old('role_onboarding.'.$key.'.enabled', $custom['enabled'] ?? true)) class="size-4 rounded border-line">
                                    {{ $roleLabels[$key] ?? $key }}
                                </label>
                                <x-field :label="'Title'" :name="'role_onboarding['.$key.'][title]'" :value="old('role_onboarding.'.$key.'.title', $custom['title'] ?? $default['title'])" />
                                <label class="mt-3 block text-sm font-semibold" for="role-steps-{{ $key }}">Tips (one per line)</label>
                                <textarea id="role-steps-{{ $key }}" name="role_onboarding[{{ $key }}][steps]" rows="4" class="field mt-1.5">{{ old('role_onboarding.'.$key.'.steps', isset($custom['steps']) ? implode("\n", $custom['steps']) : implode("\n", $default['steps'])) }}</textarea>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </x-tab>
        </x-tabs>

        <div class="sticky bottom-[calc(0.75rem+var(--tabbar-h,0px))] z-10 flex flex-wrap gap-2 rounded-2xl border border-line bg-white/95 p-3 shadow-panel backdrop-blur sm:bottom-4">
            <x-button variant="brand" size="lg" icon="check">Save settings</x-button>
            <p class="m-0 self-center text-sm text-muted">Saves every tab — subscription invoices are paid separately.</p>
        </div>
    </form>
</x-layouts.app>
