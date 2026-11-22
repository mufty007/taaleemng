<x-layouts.app title="Reports">
    <x-page-header kicker="System" title="Reports" description="Detailed reports on students, results, money and staff for management, board and PTA meetings. Each one prints on your letterhead and downloads as a spreadsheet." />

    @foreach ($groups as $group => $reports)
        <section class="flex flex-col gap-3" aria-labelledby="group-{{ $loop->index }}">
            <h2 id="group-{{ $loop->index }}" class="m-0 text-xs font-semibold tracking-[0.08em] text-muted uppercase">{{ $group }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2" data-stagger>
                @foreach ($reports as $report)
                    <a href="{{ route('school.reports.show', $report['key']) }}" class="group flex gap-4 rounded-3xl border border-line bg-white p-5 transition hover:border-ink">
                        <span class="grid size-12 flex-none place-items-center rounded-2xl bg-brand-50 text-brand-600">
                            <x-dynamic-component :component="'lucide-'.$report['icon']" class="size-6" aria-hidden="true" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-lg font-semibold">{{ $report['title'] }}</span>
                                <x-badge>{{ match ($report['period']) { 'term' => 'By term', 'session' => 'By session', default => 'As of today' } }}</x-badge>
                            </span>
                            <span class="text-sm text-muted">{{ $report['body'] }}</span>
                            <ul class="m-0 mt-2 flex list-none flex-col gap-1 p-0 text-[13px] text-ink-700">
                                @foreach ($report['covers'] as $item)
                                    <li class="flex items-start gap-2">
                                        <x-lucide-check class="mt-0.5 size-3.5 flex-none text-naija" aria-hidden="true" />
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</x-layouts.app>
