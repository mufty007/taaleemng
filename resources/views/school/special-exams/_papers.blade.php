{{-- How each paper went. Shared by the results page and the broadsheet. --}}
<x-report.panel title="Papers" description="Average, best and lowest mark, and the share of candidates at or above the pass mark" :padding="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Paper</th>
                    <th scope="col" class="px-3 py-3 text-right">Sat</th>
                    <th scope="col" class="px-3 py-3 text-right">Average</th>
                    <th scope="col" class="px-3 py-3 text-right">Range</th>
                    <th scope="col" class="px-5 py-3 text-right">Passed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results->paperResults as $row)
                    <tr class="border-t border-line">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['paper']->name }} <span class="text-xs font-normal text-muted">/ {{ $row['paper']->max_score }}</span></th>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['sat'] }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $row['average'] ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-right text-muted tabular-nums">{{ $row['sat'] > 0 ? $row['lowest'].'–'.$row['highest'] : '—' }}</td>
                        <td class="px-5 py-2.5 text-right"><x-report.meter :value="$row['pass_rate']" :alert-below="50" tone="naija" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-report.panel>
