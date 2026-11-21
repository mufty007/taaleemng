@php
    use App\Support\Money;
    $outstanding = $summary['billed'] - $summary['collected'];
    $rate = $summary['billed'] > 0 ? (int) round($summary['collected'] / $summary['billed'] * 100) : 0;
@endphp

<x-layouts.app title="Invoices">
    <x-page-header kicker="Finance" title="Invoices" :description="$term ? $term->name.' · '.$term->academicSession->name : 'Start a session first.'">
        <x-slot:actions>
            @if ($terms)
                <form method="GET">
                    <label for="term" class="sr-only">Term</label>
                    <select id="term" name="term" class="field w-64 bg-white py-2" x-data x-on:change="$el.form.submit()">
                        @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($term?->id === $id)>{{ $label }}</option>@endforeach
                    </select>
                </form>
            @endif
            @can('fees.manage')
                @if ($term)
                    <x-button icon="file-plus-2" type="button" x-data x-on:click="$dispatch('open-drawer', 'generate')">Issue invoices</x-button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Term summary">
        <x-stat-card label="Billed" :value="Money::format($summary['billed'])" :hint="number_format($summary['invoices']).' invoices'" icon="receipt" />
        <x-stat-card label="Collected" :value="Money::format($summary['collected'])" :hint="$rate.'% of what was billed'" icon="banknote-arrow-down" tone="naija" />
        <x-stat-card label="Outstanding" :value="Money::format($outstanding)" :hint="($statusCounts['unpaid'] ?? 0).' unpaid · '.($statusCounts['part_paid'] ?? 0).' part paid'" icon="hand-coins" tone="gold" />
        <x-stat-card label="Not yet billed" :value="number_format($unbilled)" hint="Enrolled students without an invoice this term" icon="user-round-x" tone="sky" />
    </section>

    <nav class="-mx-1 overflow-x-auto px-1" aria-label="Invoice status">
        <div class="inline-flex min-w-full gap-1 rounded-2xl border border-line bg-white p-1.5 sm:min-w-0">
            @foreach ($filters as $key => $label)
                @php($count = $key === 'all' ? $statusCounts->sum() : ($statusCounts[$key] ?? 0))
                <a href="{{ route('school.invoices.index', array_filter(['term' => $term?->id, 'status' => $key === 'all' ? null : $key, 'class' => $armId, 'q' => $search ?: null])) }}" @class([
                    'flex flex-none items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold whitespace-nowrap transition',
                    'bg-ink text-white' => $filter === $key,
                    'text-muted hover:text-ink' => $filter !== $key,
                ]) @if ($filter === $key) aria-current="page" @endif>
                    {{ $label }} <span @class(['rounded-full px-1.5 py-0.5 text-[11px]', 'bg-white/20' => $filter === $key, 'bg-chip' => $filter !== $key])>{{ $count }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4" role="search">
            <input type="hidden" name="term" value="{{ $term?->id }}">
            @if ($filter !== 'all')<input type="hidden" name="status" value="{{ $filter }}">@endif
            <div class="flex min-w-60 flex-1 flex-col gap-1.5">
                <label for="q" class="text-sm font-semibold">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Student, admission no. or invoice no." class="field">
            </div>
            <div class="flex w-44 flex-col gap-1.5">
                <label for="class" class="text-sm font-semibold">Class</label>
                <select id="class" name="class" class="field">
                    <option value="">All classes</option>
                    @foreach ($arms as $id => $name)<option value="{{ $id }}" @selected($armId === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <x-button variant="secondary" icon="search">Filter</x-button>
        </form>

        @if ($invoices->isEmpty())
            <x-empty-state icon="receipt" :title="$summary['invoices'] === 0 ? 'No invoices for this term yet' : 'No invoices match'" :body="$summary['invoices'] === 0 ? 'Set the fee schedule, then issue invoices for every class in one go.' : null" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Student</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Class</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Invoice</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Total</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Paid</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Balance</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr class="border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3">
                                    <a href="{{ route('school.invoices.show', $invoice) }}" class="font-medium hover:text-brand-600">{{ $invoice->student->registerName() }}</a>
                                    <span class="block font-mono text-xs text-muted">{{ $invoice->student->admission_no }}</span>
                                </td>
                                <td class="px-3 py-3">{{ $invoice->enrolment->classArm->displayName() }}</td>
                                <td class="px-3 py-3 font-mono text-sm">{{ $invoice->invoice_no }}</td>
                                <td class="px-3 py-3 text-right">{{ Money::format($invoice->total) }}</td>
                                <td class="px-3 py-3 text-right text-naija-700">{{ $invoice->amount_paid ? Money::format($invoice->amount_paid) : '—' }}</td>
                                <td class="px-3 py-3 text-right font-semibold">{{ $invoice->balance() ? Money::format($invoice->balance()) : '—' }}</td>
                                <td class="px-3 py-3"><x-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-badge></td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('school.invoices.show', $invoice) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Open <x-lucide-chevron-right class="size-4" aria-hidden="true" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())
                <div class="border-t border-line px-5 py-3">{{ $invoices->links() }}</div>
            @endif
        @endif
    </x-card>

    @can('fees.manage')
        @if ($term)
            <x-drawer name="generate" :title="'Issue '.$term->name.' invoices'" description="Every enrolled student gets an invoice from the fee schedule. Students who already have one are skipped, so you can run this again when new students join.">
                <form method="POST" action="{{ route('school.invoices.generate') }}" class="flex flex-col gap-5" id="generate-form">
                    @csrf
                    <input type="hidden" name="term_id" value="{{ $term->id }}">
                    <fieldset class="m-0 border-0 p-0">
                        <legend class="mb-2 text-sm font-semibold">Classes</legend>
                        <p class="m-0 mb-3 text-[13px] text-muted">Leave all unticked to bill every class.</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($levels as $id => $name)
                                <label class="flex items-center gap-2.5 rounded-xl border border-line px-3 py-2 text-sm has-checked:border-brand-600 has-checked:bg-brand-50">
                                    <input type="checkbox" name="class_level_ids[]" value="{{ $id }}" class="size-4 accent-brand-600"> {{ $name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <x-field label="Pay by" name="due_on" type="date" :value="$term->starts_on->copy()->addWeeks(2)->toDateString()" hint="Printed on the invoice." />
                </form>
                <x-slot:footer>
                    <x-button variant="secondary" :href="route('school.fees.setup', ['term' => $term->id])" icon="settings-2">Check fee schedule</x-button>
                    <x-button variant="brand" icon="file-plus-2" form="generate-form">Issue invoices</x-button>
                </x-slot:footer>
            </x-drawer>
        @endif
    @endcan
</x-layouts.app>
