{{-- Attendance. Shared by the report screen and its printout. --}}
<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="Student presence" :value="$students['rate'] !== null ? $students['rate'].'%' : '—'" :hint="number_format($students['present']).' of '.number_format($students['marked']).' marks'" tone="naija" />
    <x-report.stat label="Absences" :value="number_format($students['absent'])" :hint="number_format($students['late']).' late arrivals'" tone="gold" />
    <x-report.stat label="Chronic absentees" :value="number_format($absenteeCount)" :hint="'Below '.$threshold.'% this term'" tone="danger" />
    <x-report.stat label="Staff presence" :value="$staffSummary['rate'] !== null ? $staffSummary['rate'].'%' : '—'" :hint="$days.' school '.Str::plural('day', $days).' on register'" tone="sky" />
</section>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] print:grid-cols-[3fr_2fr] print:gap-4">
    <x-report.panel title="Absences week by week" description="Student absences each week of term. Hover a week for its presence rate.">
        <x-report.columns
            :bars="array_map(fn ($week) => ['label' => $week['label'], 'value' => $week['absent'], 'display' => number_format($week['absent']).' absences · '.$week['rate'].'% present'], $weeks)"
            tone="gold"
            label="Absences by week"
        />
    </x-report.panel>
    <x-report.panel title="Day of the week" description="Which days students stay away">
        <ul class="m-0 flex list-none flex-col gap-2.5 p-0 text-sm">
            @foreach ($weekdays as $day)
                <li class="flex items-center justify-between gap-3">
                    <span>{{ $day['label'] }} <span class="text-xs text-muted">· {{ $day['days'] }} {{ Str::plural('day', $day['days']) }}, {{ $day['late'] }} late</span></span>
                    <x-report.meter :value="$day['rate']" :alert-below="$threshold" tone="naija" />
                </li>
            @endforeach
        </ul>
    </x-report.panel>
</div>

<x-report.panel title="By class" description="Presence, absences and lateness for every class this term" :padding="false" :keep="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Class</th>
                    <th scope="col" class="px-3 py-3 text-right">Students</th>
                    <th scope="col" class="px-3 py-3 text-right">Days taken</th>
                    <th scope="col" class="px-3 py-3 text-right">Absences</th>
                    <th scope="col" class="px-3 py-3 text-right">Late</th>
                    <th scope="col" class="px-5 py-3 text-right">Presence</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $row)
                    <tr class="border-t border-line break-inside-avoid">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['arm']->displayName() }}</th>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['students'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['days_taken'] ?: '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['absent'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['late'] }}</td>
                        <td class="px-5 py-2.5 text-right"><x-report.meter :value="$row['rate']" :alert-below="$threshold" tone="naija" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-muted">No classes have students this session.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-report.panel>

<div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="Chronic absentees" :description="'Below '.$threshold.'% presence'.($absenteeCount > $absentees->count() ? ', lowest '.$absentees->count().' of '.$absenteeCount.' shown' : '')" :padding="false" :keep="false">
        @if ($absentees->isEmpty())
            <x-empty-state icon="user-round-check" title="No chronic absentees" :body="'Every marked student is at or above '.$threshold.'% this term.'" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0">
                @foreach ($absentees as $row)
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm break-inside-avoid">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $row['student'] }}</span>
                            <span class="block text-xs text-muted">{{ $row['class'] }} · absent {{ $row['absent'] }} of {{ $row['days'] }} days · late {{ $row['late'] }}</span>
                        </span>
                        <span class="font-bold text-danger tabular-nums">{{ $row['rate'] }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-report.panel>

    <div class="flex flex-col gap-6 print:gap-4">
        <x-report.panel title="Lowest days" description="School days with the most students away" :padding="false">
            @if ($lowestDays->isEmpty())
                <x-empty-state icon="calendar-x" title="No registers taken yet" />
            @else
                <ul class="m-0 list-none divide-y divide-line p-0">
                    @foreach ($lowestDays as $day)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                            <span><span class="font-semibold">{{ $day['date']->format('D j M') }}</span> <span class="text-xs text-muted">· {{ $day['absent'] }} absent</span></span>
                            <x-report.meter :value="$day['rate']" :alert-below="$threshold" tone="naija" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-report.panel>

        <x-report.panel title="Staff to watch" description="Lowest staff presence this term" :padding="false">
            @if ($staffRows->isEmpty())
                <x-empty-state icon="briefcase" title="No staff marks yet" body="Take the staff register to see presence rates here." />
            @else
                <ul class="m-0 list-none divide-y divide-line p-0">
                    @foreach ($staffRows as $row)
                        <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $row['staff'] }}</span>
                                <span class="block text-xs text-muted">{{ $row['designation'] ?? $row['staff_no'] }} · {{ $row['absent'] }} absent, {{ $row['late'] }} late</span>
                            </span>
                            <x-report.meter :value="$row['rate']" :alert-below="$threshold" tone="sky" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-report.panel>
    </div>
</div>
