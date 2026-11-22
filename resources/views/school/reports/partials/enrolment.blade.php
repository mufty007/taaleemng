{{-- Enrolment & demographics. Shared by the report screen and its printout. --}}
<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="On roll" :value="number_format($stats['on_roll'])" :hint="$stats['arms'].' classes · about '.($stats['average_class'] ?? 0).' a class'" />
    <x-report.stat label="Boys · Girls" :value="number_format($stats['boys']).' · '.number_format($stats['girls'])" :hint="$stats['on_roll'] > 0 ? round($stats['girls'] / $stats['on_roll'] * 100).'% girls' : null" tone="sky" />
    <x-report.stat label="New this session" :value="number_format($stats['new'])" :hint="number_format($stats['on_roll'] - $stats['new']).' returning'" tone="naija" />
    <x-report.stat label="Seats filled" :value="$stats['seats_filled'] !== null ? $stats['seats_filled'].'%' : '—'" :hint="$stats['capacity'] > 0 ? 'of '.number_format($stats['capacity']).' seats' : 'Set class capacities to track this'" tone="gold" />
</section>

<x-report.panel title="By section" description="Share of the school in each section" :padding="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[520px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Section</th>
                    <th scope="col" class="px-3 py-3 text-right">Classes</th>
                    <th scope="col" class="px-3 py-3 text-right">Boys</th>
                    <th scope="col" class="px-3 py-3 text-right">Girls</th>
                    <th scope="col" class="px-3 py-3 text-right">Students</th>
                    <th scope="col" class="px-5 py-3 text-right">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sections as $row)
                    <tr class="border-t border-line">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['section'] }}</th>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['arms'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['boys']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['girls']) }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($row['total']) }}</td>
                        <td class="px-5 py-2.5 text-right"><x-report.meter :value="$row['share']" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-muted">No students are enrolled in this session yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-report.panel>

<x-report.panel title="By class" description="Every class arm with students this session" :padding="false" :keep="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Class</th>
                    <th scope="col" class="px-3 py-3">Form teacher</th>
                    <th scope="col" class="px-3 py-3 text-right">Boys</th>
                    <th scope="col" class="px-3 py-3 text-right">Girls</th>
                    <th scope="col" class="px-3 py-3 text-right">Total</th>
                    <th scope="col" class="px-3 py-3 text-right">New</th>
                    <th scope="col" class="px-3 py-3 text-right">Avg age</th>
                    <th scope="col" class="px-5 py-3 text-right">Capacity</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($classes as $row)
                    <tr class="border-t border-line break-inside-avoid">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['arm']->displayName() }}</th>
                        <td class="px-3 py-2.5 text-muted">{{ $row['form_teacher'] ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['boys'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['girls'] }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $row['total'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['new'] ?: '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['average_age'] ?? '—' }}</td>
                        <td class="px-5 py-2.5 text-right">
                            @if ($row['capacity'])
                                <span class="text-xs text-muted">{{ $row['total'] }}/{{ $row['capacity'] }}</span>
                                <x-report.meter :value="$row['fill']" class="ml-1" />
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @if ($classes->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-line">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold" colspan="2">Whole school</th>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($stats['boys']) }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($stats['girls']) }}</td>
                        <td class="px-3 py-2.5 text-right font-bold tabular-nums">{{ number_format($stats['on_roll']) }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($stats['new']) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-report.panel>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="Ages" description="Age today, students on roll">
        <x-report.bars :rows="$ages" />
    </x-report.panel>
    <x-report.panel title="Enrolment across sessions" description="Students on roll each session">
        <x-report.columns
            :bars="array_map(fn ($row) => ['label' => $row['session'], 'value' => $row['students'], 'display' => number_format($row['students']).' students', 'current' => $row['current']], $trend)"
            label="Students on roll by session"
        />
    </x-report.panel>
    <x-report.panel title="Religion">
        <x-report.bars :rows="$religions" tone="naija" />
    </x-report.panel>
    <x-report.panel title="State of origin" description="The largest few, the rest grouped">
        <x-report.bars :rows="$states" tone="sky" />
    </x-report.panel>
</div>

<x-report.panel title="Enrolment status" description="Including students who left during the session">
    <x-report.bars :rows="$statuses" tone="gold" />
    @if ($stats['withdrawn'] > 0)
        <p class="m-0 mt-3 text-sm text-muted">{{ $stats['withdrawn'] }} {{ Str::plural('student', $stats['withdrawn']) }} withdrew this session and are not counted on roll.</p>
    @endif
</x-report.panel>
