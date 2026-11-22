{{-- Staff & workload. Shared by the report screen and its printout. --}}
@use('App\Support\Money')

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
    <x-report.stat label="Active staff" :value="number_format($stats['active'])" :hint="$stats['on_leave'].' on leave · '.$stats['exited'].' left'" />
    <x-report.stat label="Teaching · non-teaching" :value="$stats['teaching'].' · '.$stats['non_teaching']" :hint="$stats['men'].' men, '.$stats['women'].' women'" tone="sky" />
    @if ($showPay)
        <x-report.stat label="Monthly wage bill" :value="Money::format($stats['wage_bill'])" :hint="$stats['unsalaried'] > 0 ? 'Net pay · '.$stats['unsalaried'].' with no salary on file' : 'Net pay from salaries on file'" tone="gold" />
    @else
        <x-report.stat label="Average service" :value="$stats['average_service'] !== null ? $stats['average_service'].' years' : '—'" hint="Active staff with a start date" tone="gold" />
    @endif
    <x-report.stat label="Staff presence" :value="$stats['presence'] !== null ? $stats['presence'].'%' : '—'" :hint="$term ? $term->name.' register' : 'No current term'" tone="naija" />
</section>

<x-report.panel title="By department" :padding="false">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] border-collapse text-left text-sm print:min-w-0">
            <thead>
                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                    <th scope="col" class="px-5 py-3">Department</th>
                    <th scope="col" class="px-3 py-3 text-right">Staff</th>
                    <th scope="col" class="px-3 py-3 text-right">Men</th>
                    <th scope="col" class="px-3 py-3 text-right">Women</th>
                    @if ($showPay)<th scope="col" class="px-5 py-3 text-right">Monthly net pay</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $row)
                    <tr class="border-t border-line">
                        <th scope="row" class="px-5 py-2.5 text-left font-semibold">{{ $row['department'] }}</th>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['staff'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['men'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['women'] }}</td>
                        @if ($showPay)<td class="px-5 py-2.5 text-right font-mono text-xs">{{ Money::format($row['wage_bill']) }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $showPay ? 5 : 4 }}" class="px-5 py-6 text-muted">No active staff on file.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-report.panel>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3 print:grid-cols-3 print:gap-4">
    <x-report.panel title="Employment">
        <x-report.bars :rows="$contracts" tone="sky" />
    </x-report.panel>
    <x-report.panel title="Length of service">
        <x-report.bars :rows="$service" />
    </x-report.panel>
    <x-report.panel title="Qualifications" description="Highest qualification on file">
        <x-report.bars :rows="$qualifications" tone="naija" />
    </x-report.panel>
</div>

<x-report.panel title="Teaching load" description="Subjects, classes and timetabled periods a week for each teacher, busiest first" :padding="false" :keep="false">
    @if ($load->isEmpty())
        <x-empty-state icon="book-open" title="No teachers assigned to subjects yet" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] border-collapse text-left text-sm print:min-w-0">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3">Teacher</th>
                        <th scope="col" class="px-3 py-3 text-right">Subjects</th>
                        <th scope="col" class="px-3 py-3 text-right">Classes</th>
                        <th scope="col" class="px-3 py-3 text-right">Periods a week</th>
                        <th scope="col" class="px-5 py-3">Form class</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($load as $row)
                        <tr class="border-t border-line break-inside-avoid">
                            <th scope="row" class="px-5 py-2.5 text-left">
                                <a href="{{ route('school.staff.show', $row['staff']) }}" class="font-semibold hover:text-brand-600">{{ $row['staff']->fullName() }}</a>
                                <span class="block text-xs font-normal text-muted">{{ $row['staff']->designation }}</span>
                            </th>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['subjects'] }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['classes'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $row['periods'] ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-muted">{{ $row['form_class'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-report.panel>

<x-report.panel title="Subjects with no teacher" :description="$unassigned->isEmpty() ? 'Every subject in every class has a teacher.' : $unassigned->count().' subject '.Str::plural('class', $unassigned->count()).' need a teacher'" :padding="false">
    @if ($unassigned->isNotEmpty())
        <x-slot:actions>
            <a href="{{ route('school.subjects.index') }}" class="font-semibold text-brand-600 hover:text-ink">Assign teachers</a>
        </x-slot:actions>
        <ul class="m-0 grid list-none grid-cols-1 gap-x-6 p-0 text-sm sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3">
            @foreach ($unassigned->take(45) as $classSubject)
                <li class="border-b border-line px-5 py-2"><span class="font-medium">{{ $classSubject->classArm->displayName() }}</span> <span class="text-muted">· {{ $classSubject->subject->name }}</span></li>
            @endforeach
        </ul>
        @if ($unassigned->count() > 45)
            <p class="m-0 px-5 py-3 text-sm text-muted">and {{ $unassigned->count() - 45 }} more.</p>
        @endif
    @endif
</x-report.panel>
