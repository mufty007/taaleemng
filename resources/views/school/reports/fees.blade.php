@php use App\Support\Money; @endphp

<x-layouts.app title="Fees report">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.reports.index') }}" class="hover:text-ink">Reports</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Fees</span>
    </div>

    <x-page-header kicker="Reports" title="Fees & debtors" :description="$term->name.' · '.$term->academicSession->name">
        <x-slot:actions>
            <form method="GET">
                <label for="term" class="sr-only">Term</label>
                <select id="term" name="term" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($term->id === $id)>{{ $label }}</option>@endforeach
                </select>
            </form>
            <x-button data-native variant="secondary" icon="printer" type="button" x-data x-on:click="window.print()">Print</x-button>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Collected this term" :value="Money::format($collected)" icon="banknote-arrow-down" tone="naija" />
        <x-stat-card label="Payments" :value="(string) $payments" icon="receipt" />
        <x-stat-card label="Outstanding" :value="Money::format($owed)" icon="hand-coins" tone="gold" />
        <x-stat-card label="Debtors" :value="(string) $debtors" icon="users" />
    </section>

    <x-card>
        <h2 class="m-0 text-base font-semibold">Collections by method</h2>
        @if ($byMethod === [])
            <p class="m-0 mt-3 text-sm text-muted">No payments recorded in this term yet.</p>
        @else
            <ul class="m-0 mt-4 list-none divide-y divide-line p-0">
                @foreach ($byMethod as $method => $total)
                    <li class="flex items-center justify-between gap-3 py-2.5 text-[15px]">
                        <span>{{ $method }}</span>
                        <span class="font-semibold">{{ Money::format((int) $total) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="m-0 mt-4 text-sm text-muted"><a href="{{ route('school.debtors.index') }}" class="font-semibold text-brand-600 hover:text-ink">Open debtors list</a> for names and export.</p>
    </x-card>
</x-layouts.app>
