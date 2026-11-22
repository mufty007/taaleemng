<x-layouts.app :title="$report['title']">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.reports.index') }}" class="hover:text-ink">Reports</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $report['title'] }}</span>
    </div>

    <x-page-header kicker="Reports" :title="$report['title']" :description="$periodLabel.'. '.$report['body']">
        <x-slot:actions>
            @if ($periodOptions !== [])
                <form method="GET">
                    <label for="period" class="sr-only">{{ $report['period'] === 'term' ? 'Term' : 'Session' }}</label>
                    <select id="period" name="{{ $report['period'] }}" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                        @foreach ($periodOptions as $id => $label)
                            <option value="{{ $id }}" @selected($selectedPeriod === $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <x-button data-native variant="secondary" icon="file-spreadsheet" :href="route('school.reports.show', [$report['key'], ...$periodQuery, 'export' => 'csv'])">CSV</x-button>
            <x-button data-native variant="secondary" icon="printer" :href="route('school.reports.print', [$report['key'], ...$periodQuery])">Print</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-6">
        @include('school.reports.partials.'.$report['key'], $figures)
    </div>
</x-layouts.app>
