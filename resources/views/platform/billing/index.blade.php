@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Billing">
    <x-page-header kicker="Platform console" title="Billing" description="What schools pay Ta’aleem: open and overdue invoices, payments received, and plans." />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-stat-card label="Paid this month" :value="Money::format($stats['paidThisMonth'])" hint="Invoices marked paid" icon="banknote-arrow-down" tone="naija" />
        <x-stat-card label="Outstanding" :value="Money::format($stats['outstanding'])" hint="Draft and sent invoices" icon="receipt" tone="brand" />
        <x-stat-card label="Overdue" :value="Money::format($stats['overdue'])" hint="Sent and past the due date" icon="circle-alert" :tone="$stats['overdue'] > 0 ? 'danger' : 'sky'" />
        <x-stat-card label="Each term (estimate)" :value="Money::format($stats['termly'])" hint="Active schools × students × price" icon="trending-up" tone="gold" />
    </div>

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
                <x-empty-state icon="receipt" title="Nothing outstanding" body="Create invoices from a school’s Billing tab." />
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
</x-layouts.app>
