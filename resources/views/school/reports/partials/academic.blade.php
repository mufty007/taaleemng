{{-- Academic performance. Shared by the report screen and its printout. --}}
@use('App\Support\Results\ResultCalculator')

@php($pass = (int) round($passMark))

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="School average" :value="$stats['average'] !== null ? $stats['average'].'%' : '—'" :hint="number_format($stats['scored']).' of '.number_format($stats['students']).' students scored'" />
    <x-report.stat label="At or above pass mark" :value="$stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '—'" :hint="'Pass mark '.$pass.'%'.($stats['credit_rate'] !== null ? ' · '.$stats['credit_rate'].'% of subject scores at credit' : '')" tone="naija" />
    <x-report.stat label="Scores entered" :value="$stats['completion'] !== null ? $stats['completion'].'%' : '—'" hint="Of every score expected this term" tone="gold" />
    <x-report.stat label="Results published" :value="$stats['published'].' of '.$stats['classes']" hint="Classes parents can see" tone="sky" />
</section>

@if ($stats['completion'] !== null && $stats['completion'] < 100)
    <p class="m-0 rounded-xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold">
        Not every score is in yet, so every figure is a percentage of the marks entered so far, and grades are worked out from those percentages.
    </p>
@endif

<x-report.panel title="By class" description="Average of each student’s percentage, the best and lowest student, the share of students at or above the pass mark, and the share of subject scores at credit" :padding="false" :keep="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Class</th>
                    <th scope="col" class="px-3 py-3 text-right">Scored</th>
                    <th scope="col" class="px-3 py-3 text-right">Average</th>
                    <th scope="col" class="px-3 py-3 text-right">Best</th>
                    <th scope="col" class="px-3 py-3 text-right">Lowest</th>
                    <th scope="col" class="px-3 py-3 text-right">Pass rate</th>
                    <th scope="col" class="px-3 py-3 text-right">Credit</th>
                    <th scope="col" class="px-3 py-3 text-right">Scores in</th>
                    <th scope="col" class="px-5 py-3 text-right">Published</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $row)
                    <tr class="border-t border-line break-inside-avoid">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">
                            <a href="{{ route('school.results.show', $row['arm']) }}" class="hover:text-brand-600">{{ $row['arm']->displayName() }}</a>
                        </th>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['scored'] }}/{{ $row['students'] }}</td>
                        <td @class(['px-3 py-2.5 text-right font-semibold tabular-nums', 'text-danger' => $row['average'] !== null && $row['average'] < $passMark])>{{ $row['average'] !== null ? $row['average'].'%' : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['highest'] !== null ? $row['highest'].'%' : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['lowest'] !== null ? $row['lowest'].'%' : '—' }}</td>
                        <td class="px-3 py-2.5 text-right"><x-report.meter :value="$row['pass_rate']" :alert-below="50" tone="naija" /></td>
                        <td class="px-3 py-2.5 text-right"><x-report.meter :value="$row['credit_rate']" :alert-below="50" tone="sky" /></td>
                        <td class="px-3 py-2.5 text-right"><x-report.meter :value="$row['completion']" :alert-below="50" /></td>
                        <td class="px-5 py-2.5 text-right">
                            @if ($row['published'])
                                <x-badge tone="naija">Published</x-badge>
                            @else
                                <x-badge>Not yet</x-badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-6 text-muted">No classes have students this session.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-report.panel>

<x-report.panel title="By subject" description="Every class that takes the subject, strongest first" :padding="false" :keep="false">
    @if ($subjects->isEmpty())
        <x-empty-state icon="file-pen-line" title="No scores yet" body="Subject averages appear once teachers start entering scores for this term." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] border-collapse text-left text-sm print:min-w-0">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3">Subject</th>
                        <th scope="col" class="px-3 py-3 text-right">Classes</th>
                        <th scope="col" class="px-3 py-3 text-right">Students</th>
                        <th scope="col" class="px-3 py-3 text-right">Average</th>
                        <th scope="col" class="px-3 py-3 text-right">Range</th>
                        <th scope="col" class="px-3 py-3 text-right">Pass rate</th>
                        <th scope="col" class="px-5 py-3 text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $row)
                        <tr class="border-t border-line break-inside-avoid">
                            <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['subject'] }}</th>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['classes'] }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($row['entries']) }}</td>
                            <td @class(['px-3 py-2.5 text-right font-semibold tabular-nums', 'text-danger' => $row['average'] < $passMark])>{{ $row['average'] }}%</td>
                            <td class="px-3 py-2.5 text-right text-muted tabular-nums">{{ round($row['lowest']) }}–{{ round($row['highest']) }}%</td>
                            <td class="px-3 py-2.5 text-right"><x-report.meter :value="$row['pass_rate']" :alert-below="50" tone="naija" /></td>
                            <td class="px-5 py-2.5 text-right"><x-report.meter :value="$row['credit_rate']" :alert-below="50" tone="sky" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-report.panel>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="Grade spread" description="Every subject score this term, on each grading scale the school uses">
        @forelse ($grades as $scale => $rows)
            <h3 @class(['m-0 mb-2 text-xs font-semibold tracking-[0.05em] text-muted uppercase', 'mt-5' => ! $loop->first])>{{ $scale }}</h3>
            <x-report.bars :rows="$rows" />
        @empty
            <p class="m-0 text-sm text-muted">No scores entered yet.</p>
        @endforelse
    </x-report.panel>

    <x-report.panel title="Boys and girls">
        <dl class="m-0 grid grid-cols-2 gap-4">
            @foreach ($genders as $row)
                <div class="rounded-xl bg-canvas p-4">
                    <dt class="text-sm text-muted">{{ $row['label'] }} · {{ $row['students'] }}</dt>
                    <dd class="m-0 mt-1 text-2xl font-bold tabular-nums">{{ $row['average'] !== null ? $row['average'].'%' : '—' }}</dd>
                    <dd class="m-0 text-[13px] text-muted">{{ $row['pass_rate'] !== null ? $row['pass_rate'].'% at or above '.$pass.'%' : 'No scores yet' }}</dd>
                </div>
            @endforeach
        </dl>
    </x-report.panel>
</div>

<div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-2 print:grid-cols-2 print:gap-4">
    <x-report.panel title="Top 10 students" description="Highest percentage across the school, among students scored in at least half their subjects" :padding="false">
        @if ($top->isEmpty())
            <x-empty-state icon="trophy" title="No scores yet" />
        @else
            <ol class="m-0 list-none divide-y divide-line p-0">
                @foreach ($top as $row)
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <span class="grid size-7 flex-none place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600">{{ $loop->iteration }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $row['name'] }}</span>
                            <span class="block text-xs text-muted">{{ $row['class'] }}@if ($row['position']) · {{ ResultCalculator::ordinal($row['position']) }} in class @endif · {{ $row['subjects'] }} subjects</span>
                        </span>
                        <span class="font-bold tabular-nums">{{ $row['percent'] }}%</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-report.panel>

    <x-report.panel title="Need help" :description="$atRisk->count().' '.Str::plural('student', $atRisk->count()).' under '.$pass.'% overall, or below it in '.$atRiskSubjects.' or more subjects'.($atRisk->count() > 25 ? '. Lowest 25 shown' : '')" :padding="false">
        @if ($atRisk->isEmpty())
            <x-empty-state icon="badge-check" title="Nobody needs flagging" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0">
                @foreach ($atRisk->take(25) as $row)
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $row['name'] }}</span>
                            <span class="block text-xs text-muted">{{ $row['class'] }} · below {{ $pass }}% in {{ $row['failed'] }} of {{ $row['subjects'] }} subjects</span>
                        </span>
                        <span @class(['font-bold tabular-nums', 'text-danger' => $row['percent'] < $passMark])>{{ $row['percent'] }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-report.panel>
</div>

<x-report.panel title="Subject teachers" description="Their classes’ average and how much of their score sheets are filled in, least complete first" :padding="false" :keep="false">
    @if ($teachers->isEmpty())
        <x-empty-state icon="users" title="No subjects assigned" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[620px] border-collapse text-left text-sm print:min-w-0">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3">Teacher</th>
                        <th scope="col" class="px-3 py-3 text-right">Subject classes</th>
                        <th scope="col" class="px-3 py-3 text-right">Average</th>
                        <th scope="col" class="px-3 py-3 text-right">Credit</th>
                        <th scope="col" class="px-5 py-3 text-right">Scores in</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teachers as $row)
                        <tr class="border-t border-line break-inside-avoid">
                            <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['teacher'] }}</th>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['assignments'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $row['average'] !== null ? $row['average'].'%' : '—' }}</td>
                            <td class="px-3 py-2.5 text-right"><x-report.meter :value="$row['credit_rate']" :alert-below="50" tone="sky" /></td>
                            <td class="px-5 py-2.5 text-right"><x-report.meter :value="$row['completion']" :alert-below="50" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-report.panel>
