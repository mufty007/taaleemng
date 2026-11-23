@php
    use App\Enums\BillingModel;
    use App\Support\Money;
@endphp

<x-layouts.app title="Billing">
    <x-page-header kicker="Platform console" title="Billing" description="Plans, seats, flat or per-student pricing, and what each school owes Ta’aleem." />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-stat-card label="Paid this month" :value="Money::format($stats['paidThisMonth'])" hint="Invoices marked paid" icon="banknote-arrow-down" tone="naija" />
        <x-stat-card label="Outstanding" :value="Money::format($stats['outstanding'])" hint="Draft and sent invoices" icon="receipt" tone="brand" />
        <x-stat-card label="Overdue" :value="Money::format($stats['overdue'])" hint="Sent and past the due date" icon="circle-alert" :tone="$stats['overdue'] > 0 ? 'danger' : 'sky'" />
        <x-stat-card label="Each term (estimate)" :value="Money::format($stats['termly'])" hint="Active schools at their chosen billing model" icon="trending-up" tone="gold" />
    </div>

    <x-tabs :tabs="['overview' => 'Overview', 'plans' => 'Plans', 'schools' => ['label' => 'Schools', 'count' => number_format($schools->count())]]" label="Billing sections">
        <x-tab name="overview">
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <x-card :padding="false">
                    <h2 class="m-0 border-b border-line px-5 py-4 text-base font-semibold">Open invoices</h2>
                    @forelse ($openInvoices as $invoice)
                        <a href="{{ route('platform.schools.show', $invoice->school) }}#billing" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                            <x-school-crest :school="$invoice->school" class="size-9 flex-none rounded-lg text-xs" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $invoice->school->name }}</span>
                                <span class="block truncate text-sm text-muted"><span class="font-mono">{{ $invoice->number }}</span> · {{ $invoice->period }} · due {{ $invoice->due_on->format('j M Y') }}</span>
                            </span>
                            <x-badge :tone="$invoice->isOverdue() ? 'danger' : $invoice->status->tone()">{{ $invoice->isOverdue() ? 'Overdue' : $invoice->status->label() }}</x-badge>
                            <span class="font-bold tabular-nums">{{ Money::format($invoice->amount) }}</span>
                        </a>
                    @empty
                        <x-empty-state icon="receipt" title="Nothing outstanding" body="Open a school’s Billing tab to set its plan and create an invoice." />
                    @endforelse
                </x-card>

                <aside class="flex min-w-0 flex-col gap-6">
                    <x-card>
                        <h2 class="m-0 text-base font-semibold">Schools by plan</h2>
                        <div class="mt-4">
                            <x-report.bars :rows="collect(\App\Enums\Plan::cases())->mapWithKeys(fn ($plan) => [$plan->label() => (int) ($byPlan[$plan->value] ?? 0)])->all()" />
                        </div>
                    </x-card>
                    <x-card :padding="false">
                        <h2 class="m-0 border-b border-line px-5 py-4 text-base font-semibold">Latest payments</h2>
                        @forelse ($recentPayments as $invoice)
                            <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 text-sm">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold">{{ $invoice->school->name }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $invoice->payment_method }} · {{ $invoice->paid_at?->format('j M Y') }}</span>
                                </span>
                                <span class="font-semibold tabular-nums">{{ Money::format($invoice->amount) }}</span>
                            </div>
                        @empty
                            <p class="m-0 px-5 py-4 text-sm text-muted">No payments recorded yet.</p>
                        @endforelse
                    </x-card>
                </aside>
            </div>
        </x-tab>

        <x-tab name="plans">
            <div class="flex flex-col gap-6">
                <div class="rounded-2xl border border-line bg-white px-5 py-4 text-[15px] text-muted">
                    <p class="m-0 font-semibold text-ink">Plan catalog</p>
                    <p class="m-0 mt-1">These cards are what schools see on the marketing site, signup and Settings. Click <span class="font-medium text-ink">Edit</span> to change a plan. <span class="font-medium text-ink">Open invoices keep their amount</span> — new prices apply on the next bill only. Seat limits apply when a school next adds a student or staff member.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($editablePlans as $key => $plan)
                        @php
                            $featureLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $plan['features']) ?: [])));
                        @endphp
                        <x-card class="flex h-full flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h2 class="m-0 text-lg font-bold tracking-[-0.02em]">{{ $plan['label'] }}</h2>
                                    <p class="m-0 mt-0.5 text-xs font-medium tracking-wide text-muted uppercase">{{ $key }}</p>
                                </div>
                                <x-button type="button" variant="secondary" size="sm" icon="pencil" x-data x-on:click="$dispatch('open-modal', 'edit-plan-{{ $key }}')">Edit</x-button>
                            </div>
                            @if (filled($plan['description']))
                                <p class="m-0 mt-3 text-sm text-muted">{{ $plan['description'] }}</p>
                            @endif
                            <dl class="mt-4 grid grid-cols-1 gap-2 text-sm">
                                <div class="flex justify-between gap-2 border-b border-line pb-2">
                                    <dt class="text-muted">Students</dt>
                                    <dd class="m-0 font-semibold tabular-nums">{{ $plan['max_students'] ? number_format($plan['max_students']) : 'Unlimited' }}</dd>
                                </div>
                                <div class="flex justify-between gap-2 border-b border-line pb-2">
                                    <dt class="text-muted">Staff</dt>
                                    <dd class="m-0 font-semibold tabular-nums">{{ $plan['max_staff'] ? number_format($plan['max_staff']) : 'Unlimited' }}</dd>
                                </div>
                                <div class="flex justify-between gap-2 border-b border-line pb-2">
                                    <dt class="text-muted">Flat / term</dt>
                                    <dd class="m-0 font-semibold tabular-nums">{{ filled($plan['flat_termly']) ? '₦'.number_format((float) str_replace(',', '', $plan['flat_termly']), 0) : '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-2 border-b border-line pb-2">
                                    <dt class="text-muted">Flat / year</dt>
                                    <dd class="m-0 font-semibold tabular-nums">{{ filled($plan['flat_annual']) ? '₦'.number_format((float) str_replace(',', '', $plan['flat_annual']), 0) : '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted">Per student / term</dt>
                                    <dd class="m-0 font-semibold tabular-nums">{{ filled($plan['per_student']) ? '₦'.number_format((float) str_replace(',', '', $plan['per_student']), 0) : '—' }}</dd>
                                </div>
                            </dl>
                            @if ($featureLines !== [])
                                <ul class="m-0 mt-4 list-disc space-y-1 pl-4 text-sm text-muted">
                                    @foreach (array_slice($featureLines, 0, 4) as $feature)
                                        <li>{{ $feature }}</li>
                                    @endforeach
                                    @if (count($featureLines) > 4)
                                        <li class="list-none pl-0 text-xs font-medium">+{{ count($featureLines) - 4 }} more</li>
                                    @endif
                                </ul>
                            @endif
                        </x-card>
                    @endforeach
                </div>

                @foreach ($editablePlans as $key => $plan)
                    @php
                        $bag = 'plan_'.$key;
                        $reopen = $errors->{$bag}->isNotEmpty();
                    @endphp
                    <x-modal
                        name="edit-plan-{{ $key }}"
                        :title="'Edit '.$plan['label']"
                        description="Changes update the catalog schools see. Open invoices keep their amount; new prices apply on the next bill."
                        :show="$reopen"
                        width="max-w-xl"
                    >
                        <form method="POST" action="{{ route('platform.billing.plans.update', $key) }}" class="flex flex-col gap-4">
                            @csrf
                            @method('PUT')
                            <x-field label="Display name" name="label" :bag="$bag" :value="$reopen ? old('label', $plan['label']) : $plan['label']" required />
                            <div class="flex flex-col gap-1.5">
                                <label for="desc-{{ $key }}" class="text-sm font-semibold">Description</label>
                                <textarea id="desc-{{ $key }}" name="description" rows="2" class="field">{{ $reopen ? old('description', $plan['description']) : $plan['description'] }}</textarea>
                                @error('description', $bag)<p class="m-0 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-field label="Max students" name="max_students" type="number" :bag="$bag" :value="$reopen ? old('max_students', $plan['max_students']) : $plan['max_students']" placeholder="Unlimited" />
                                <x-field label="Max staff" name="max_staff" type="number" :bag="$bag" :value="$reopen ? old('max_staff', $plan['max_staff']) : $plan['max_staff']" placeholder="Unlimited" />
                                <x-field label="Flat fee / term (₦)" name="flat_termly" :bag="$bag" inputmode="decimal" :value="$reopen ? old('flat_termly', $plan['flat_termly']) : $plan['flat_termly']" />
                                <x-field label="Flat fee / year (₦)" name="flat_annual" :bag="$bag" inputmode="decimal" :value="$reopen ? old('flat_annual', $plan['flat_annual']) : $plan['flat_annual']" />
                                <div class="sm:col-span-2">
                                    <x-field label="Per student / term (₦)" name="per_student" :bag="$bag" inputmode="decimal" :value="$reopen ? old('per_student', $plan['per_student']) : $plan['per_student']" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="feat-{{ $key }}" class="text-sm font-semibold">Features <span class="font-normal text-muted">(one per line)</span></label>
                                <textarea id="feat-{{ $key }}" name="features" rows="4" class="field">{{ $reopen ? old('features', $plan['features']) : $plan['features'] }}</textarea>
                                @error('features', $bag)<p class="m-0 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </div>

                            <div class="rounded-xl border border-line bg-canvas px-4 py-3" x-data="{ notify: {{ ($reopen && old('notify_schools')) ? 'true' : 'false' }} }">
                                <p class="m-0 text-sm font-semibold text-ink">Tell schools (optional)</p>
                                <label class="mt-2 flex items-start gap-2 text-sm">
                                    <input type="hidden" name="notify_schools" value="0">
                                    <input type="checkbox" name="notify_schools" value="1" class="mt-0.5 size-4 rounded border-line text-brand-600" x-model="notify" @checked($reopen && old('notify_schools'))>
                                    <span>Notify school admins that this plan is changing</span>
                                </label>
                                <div x-show="notify" x-cloak class="mt-3 grid grid-cols-1 gap-3">
                                    <x-field label="Notice title" name="notice_title" :bag="$bag" :value="$reopen ? old('notice_title', 'We’re updating Ta’aleem plans') : 'We’re updating Ta’aleem plans'" />
                                    <div class="flex flex-col gap-1.5">
                                        <label for="notice_body_{{ $key }}" class="text-sm font-semibold">Notice message</label>
                                        <textarea id="notice_body_{{ $key }}" name="notice_body" rows="3" class="field">{{ $reopen ? old('notice_body', 'We’re updating our subscription plans. Your current invoices stay as they are; the new prices apply on your next bill.') : 'We’re updating our subscription plans. Your current invoices stay as they are; the new prices apply on your next bill.' }}</textarea>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="hidden" name="email_admins" value="0">
                                        <input type="checkbox" name="email_admins" value="1" class="size-4 rounded border-line text-brand-600" @checked(! $reopen || old('email_admins', true))>
                                        Also email every school admin
                                    </label>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <x-button variant="brand" icon="check">Save plan</x-button>
                                <x-button type="button" variant="ghost" x-data x-on:click="$dispatch('close-modal', 'edit-plan-{{ $key }}')">Cancel</x-button>
                            </div>
                        </form>
                    </x-modal>
                @endforeach
            </div>
        </x-tab>

        <x-tab name="schools">
            <x-card :padding="false">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="m-0 text-base font-semibold">School subscriptions</h2>
                        <p class="m-0 mt-0.5 text-sm text-muted">Open a school to change its plan, billing model, seat overrides and invoices.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[880px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="border-b border-line text-xs font-semibold tracking-wide text-muted uppercase">
                                <th class="px-5 py-3 font-semibold">School</th>
                                <th class="px-3 py-3 font-semibold">Plan</th>
                                <th class="px-3 py-3 font-semibold">Billing</th>
                                <th class="px-3 py-3 text-right font-semibold">Students</th>
                                <th class="px-3 py-3 text-right font-semibold">Staff</th>
                                <th class="px-5 py-3 text-right font-semibold">Next bill</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($schools as $school)
                                @php
                                    $model = $school->billing_model ?? BillingModel::Flat;
                                    $next = \App\Support\Billing\PlanCatalog::invoiceAmount(
                                        $school->plan,
                                        $model,
                                        $school->billing_cycle ?: 'termly',
                                        (int) $school->students_count,
                                        $school->price_per_student,
                                    );
                                    $studentCap = $school->max_students ?? $school->plan->maxStudents();
                                    $staffCap = $school->max_staff ?? $school->plan->maxStaff();
                                @endphp
                                <tr class="border-b border-line last:border-b-0 hover:bg-canvas">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('platform.schools.show', $school) }}#billing" class="flex items-center gap-3 font-semibold text-ink hover:text-brand-600">
                                            <x-school-crest :school="$school" class="size-9 flex-none rounded-lg text-xs" />
                                            <span class="min-w-0">
                                                <span class="block truncate">{{ $school->name }}</span>
                                                <span class="block text-xs font-normal text-muted">
                                                    <x-badge :tone="$school->status->tone()" class="align-middle">{{ $school->status->label() }}</x-badge>
                                                    · {{ $school->short_code }}
                                                </span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-3 py-3">{{ $school->plan->label() }}</td>
                                    <td class="px-3 py-3">
                                        <span class="block">{{ $model->label() }}</span>
                                        <span class="block text-xs text-muted">{{ $school->billing_cycle === 'annual' ? 'Yearly' : 'Termly' }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums">
                                        {{ number_format((int) $school->students_count) }}
                                        <span class="text-muted">/ {{ $studentCap ? number_format($studentCap) : '∞' }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums">
                                        {{ number_format((int) $school->staff_count) }}
                                        <span class="text-muted">/ {{ $staffCap ? number_format($staffCap) : '∞' }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold tabular-nums">
                                        {{ $next ? Money::format($next) : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-muted">No schools yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
