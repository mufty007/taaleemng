{{-- Admissions. Shared by the report screen and its printout. --}}
<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="Applications" :value="number_format($stats['applications'])" :hint="$stats['in_progress'].' still being processed'" />
    <x-report.stat label="Offered a place" :value="number_format($stats['offered'])" :hint="$stats['rejected'].' not offered'" tone="sky" />
    <x-report.stat label="Enrolled" :value="number_format($stats['enrolled'])" :hint="$stats['conversion'] !== null ? $stats['conversion'].'% of applicants'.($stats['acceptance'] !== null ? ' · '.$stats['acceptance'].'% of offers' : '') : null" tone="naija" />
    <x-report.stat label="Average exam score" :value="$stats['average_score'] !== null ? $stats['average_score'] : '—'" :hint="$stats['examined'].' sat the entrance exam'" tone="gold" />
</section>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="From application to enrolment">
        @php($applied = max(1, $funnel[0]['count']))
        <ol class="m-0 flex list-none flex-col gap-3 p-0 text-sm">
            @foreach ($funnel as $step)
                <li>
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-medium">{{ $step['label'] }}</span>
                        <span class="tabular-nums"><strong>{{ number_format($step['count']) }}</strong> <span class="text-xs text-muted">· {{ round($step['count'] / $applied * 100) }}%</span></span>
                    </div>
                    <span class="mt-1 block h-2.5 overflow-hidden rounded-full bg-chip" aria-hidden="true"><span class="block h-full rounded-full bg-brand" style="width: {{ $step['count'] / $applied * 100 }}%"></span></span>
                </li>
            @endforeach
        </ol>
    </x-report.panel>

    <x-report.panel title="Where things stand">
        <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
            @forelse ($statuses as $row)
                <li><x-badge :tone="$row['status']->tone()">{{ $row['status']->label() }} · {{ $row['count'] }}</x-badge></li>
            @empty
                <li class="text-sm text-muted">No applications for this session yet.</li>
            @endforelse
        </ul>
        <div class="mt-5 grid grid-cols-2 gap-4 text-sm">
            <div>
                <h3 class="m-0 mb-2 text-xs font-semibold tracking-[0.05em] text-muted uppercase">Applied through</h3>
                <x-report.bars :rows="$sources" tone="sky" />
            </div>
            <div>
                <h3 class="m-0 mb-2 text-xs font-semibold tracking-[0.05em] text-muted uppercase">Applicants</h3>
                <x-report.bars :rows="$genders" tone="naija" />
            </div>
        </div>
    </x-report.panel>
</div>

<x-report.panel title="By class" description="Demand and outcomes for each class applied for" :padding="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[620px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Class</th>
                    <th scope="col" class="px-3 py-3 text-right">Applied</th>
                    <th scope="col" class="px-3 py-3 text-right">Offered</th>
                    <th scope="col" class="px-3 py-3 text-right">Enrolled</th>
                    <th scope="col" class="px-3 py-3 text-right">Not offered</th>
                    <th scope="col" class="px-5 py-3 text-right">Avg score</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($levels as $row)
                    <tr class="border-t border-line">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['level']->name }}</th>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $row['applied'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['offered'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-naija-700">{{ $row['enrolled'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['rejected'] ?: '—' }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">{{ $row['average_score'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-muted">No applications for this session yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-report.panel>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="Applications by month">
        <x-report.columns
            :bars="collect($months)->map(fn ($count, $month) => ['label' => $month, 'value' => $count, 'display' => $count.' '.Str::plural('application', $count)])->values()->all()"
            label="Applications by month"
        />
    </x-report.panel>
    <x-report.panel title="Entrance exam scores" :description="$stats['examined'].' applicants with a score'">
        <x-report.bars :rows="$scores" tone="gold" empty="No exam scores recorded yet." />
    </x-report.panel>
</div>
