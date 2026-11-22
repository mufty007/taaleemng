{{-- Candidates ranked by total. Shared by the results page and the broadsheet. --}}
@use('App\Support\Results\ResultCalculator')

@if ($results->candidates->isEmpty())
    <x-empty-state icon="users" title="No candidates" body="None of the classes set for this exam have students on roll." />
@else
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm print:min-w-0" style="min-width: {{ 420 + $results->papers->count() * 72 }}px">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3 text-right">Pos.</th>
                    <th scope="col" class="px-3 py-3">Candidate</th>
                    @foreach ($results->papers as $paper)
                        <th scope="col" class="px-2 py-3 text-right" title="{{ $paper->name }} (out of {{ $paper->max_score }})">{{ $paper->subject?->code ?? Str::limit($paper->name, 8, '') }}</th>
                    @endforeach
                    <th scope="col" class="px-3 py-3 text-right">Total</th>
                    <th scope="col" class="px-3 py-3 text-right">%</th>
                    <th scope="col" class="px-5 py-3 text-right">Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results->candidates as $row)
                    <tr class="border-t border-line break-inside-avoid">
                        <td class="px-5 py-2.5 text-right font-semibold tabular-nums">{{ $row['position'] ? ResultCalculator::ordinal($row['position']) : '—' }}</td>
                        <td class="px-3 py-2.5">
                            <span class="block font-semibold">{{ $row['student']->registerName() }}</span>
                            <span class="block text-xs text-muted">{{ $row['class'] }} · {{ $row['student']->admission_no }}</span>
                        </td>
                        @foreach ($results->papers as $paper)
                            @php($mark = $row['scores'][$paper->id] ?? null)
                            <td @class(['px-2 py-2.5 text-right tabular-nums', 'text-danger' => $mark !== null && $paper->max_score > 0 && $mark / $paper->max_score * 100 < $exam->pass_mark])>{{ $mark ?? '—' }}</td>
                        @endforeach
                        <td class="px-3 py-2.5 text-right font-bold tabular-nums">{{ $row['sat'] > 0 ? $row['total'] : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['percent'] !== null ? $row['percent'].'%' : '—' }}</td>
                        <td class="px-5 py-2.5 text-right">
                            @if ($row['passed'] === null)
                                <x-badge>No marks</x-badge>
                            @elseif ($row['passed'])
                                <x-badge tone="naija">Pass</x-badge>
                            @else
                                <x-badge tone="danger">Below</x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
