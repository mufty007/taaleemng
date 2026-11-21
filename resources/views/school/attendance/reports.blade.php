<x-layouts.app title="Attendance reports">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.attendance.index') }}" class="hover:text-ink">Attendance</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Reports</span>
    </div>

    <x-page-header kicker="Attendance" title="Term report" :description="$term->name.' · '.$term->academicSession->name">
        <x-slot:actions>
            <form method="GET">
                <label for="term" class="sr-only">Term</label>
                <select id="term" name="term" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->academicSession->name }}</option>
                    @endforeach
                </select>
            </form>
            <x-button variant="ghost" icon="clipboard-check" :href="route('school.attendance.index')">Today’s registers</x-button>
            <x-button variant="ghost" icon="briefcase" :href="route('school.attendance.staff')">Staff register</x-button>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Student presence" :value="$students['rate'] !== null ? $students['rate'].'%' : '—'" :hint="$students['present'].' of '.$students['marked'].' marks'" icon="user-round-check" tone="naija" />
        <x-stat-card label="Student absences" :value="(string) $students['absent']" :hint="$students['late'].' late marks'" icon="user-round-x" tone="gold" />
        <x-stat-card label="Staff presence" :value="$staffSummary['rate'] !== null ? $staffSummary['rate'].'%' : '—'" :hint="$staffSummary['present'].' of '.$staffSummary['marked'].' marks'" icon="briefcase" tone="sky" />
        <x-stat-card label="Classes on register" :value="$classes->filter(fn ($row) => $row['days_taken'] > 0)->count().' of '.$classes->count()" icon="layers" />
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-card :padding="false">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h2 class="m-0 text-base font-semibold">Chronic absentees</h2>
                    <p class="m-0 text-sm text-muted">Below 80% presence this term</p>
                </div>
            </div>
            @if ($absentees->isEmpty())
                <x-empty-state icon="user-round-check" title="No chronic absentees" body="Every marked student is at or above 80% this term." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="px-5 py-3 font-semibold">Student</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Class</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Absent</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($absentees as $row)
                                <tr class="border-t border-line">
                                    <td class="px-5 py-3">
                                        <div class="font-semibold">{{ $row['student'] }}</div>
                                        <div class="font-mono text-xs text-muted">{{ $row['admission_no'] }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-muted">{{ $row['class'] }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-danger">{{ $row['absent'] }}</td>
                                    <td class="px-5 py-3 text-right font-semibold">{{ $row['rate'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card :padding="false">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h2 class="m-0 text-base font-semibold">Staff to watch</h2>
                    <p class="m-0 text-sm text-muted">Lowest presence rates this term</p>
                </div>
            </div>
            @if ($staffRows->isEmpty())
                <x-empty-state icon="briefcase" title="No staff marks yet" body="Take the staff register to see presence rates here." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="px-5 py-3 font-semibold">Staff</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Absent</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Late</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffRows as $row)
                                <tr class="border-t border-line">
                                    <td class="px-5 py-3">
                                        <div class="font-semibold">{{ $row['staff'] }}</div>
                                        <div class="text-xs text-muted">{{ $row['staff_no'] }}@if ($row['designation']) · {{ $row['designation'] }}@endif</div>
                                    </td>
                                    <td @class(['px-3 py-3 text-right', 'font-semibold text-danger' => $row['absent'] > 0])>{{ $row['absent'] }}</td>
                                    <td class="px-3 py-3 text-right">{{ $row['late'] }}</td>
                                    <td class="px-5 py-3 text-right font-semibold">{{ $row['rate'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <x-card :padding="false">
        <div class="border-b border-line px-5 py-4">
            <h2 class="m-0 text-base font-semibold">By class</h2>
            <p class="m-0 text-sm text-muted">Presence rate across every arm with students this session</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3 font-semibold">Class</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">Students</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">Days taken</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">Marks</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classes as $row)
                        <tr class="border-t border-line hover:bg-canvas">
                            <td class="px-5 py-3 font-semibold">{{ $row['arm']->displayName() }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['students'] }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['days_taken'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['marked'] ?: '—' }}</td>
                            <td @class(['px-5 py-3 text-right font-semibold', 'text-danger' => $row['rate'] !== null && $row['rate'] < 80])>
                                {{ $row['rate'] !== null ? $row['rate'].'%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
