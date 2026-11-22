<x-layouts.app title="Reports">
    <x-page-header kicker="System" title="Reports" description="Run snapshots of enrolment, fees, attendance and staff for the school." />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($reports as $report)
            <a href="{{ route('school.reports.show', $report['key']) }}" class="flex gap-4 rounded-3xl border border-line bg-white p-5 transition hover:border-ink">
                <span class="grid size-12 flex-none place-items-center rounded-2xl bg-brand-50 text-brand-600">
                    <x-dynamic-component :component="'lucide-'.$report['icon']" class="size-6" aria-hidden="true" />
                </span>
                <span class="min-w-0">
                    <span class="block text-lg font-semibold">{{ $report['title'] }}</span>
                    <span class="mt-1 block text-sm text-muted">{{ $report['body'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-layouts.app>
