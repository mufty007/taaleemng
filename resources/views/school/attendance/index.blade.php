@php
    $taken = $arms->filter(fn ($arm) => isset($counts[$arm->id]))->count();
    $present = $counts->sum(fn ($row) => ($row['present'] ?? 0) + ($row['late'] ?? 0));
    $marked = $counts->sum(fn ($row) => $row->sum());
@endphp

<x-layouts.app title="Attendance">
    <x-page-header kicker="Attendance" :title="$date->isToday() ? 'Today’s registers' : 'Registers for '.$date->format('l j M')" :description="$term ? $term->name.' · '.$term->academicSession->name : 'This day is not within a term.'">
        <x-slot:actions>
            @can('attendance.view')
                <x-button variant="ghost" icon="bar-chart-3" :href="route('school.attendance.reports')">Reports</x-button>
                <x-button variant="ghost" icon="briefcase" :href="route('school.attendance.staff', ['date' => $date->toDateString()])">Staff register</x-button>
                @if ($term && ! $date->isFuture() && ! $date->isWeekend() && ! $term->isClosed())
                    <form method="POST" action="{{ route('school.attendance.mark-remaining') }}" data-confirm="Mark every unmarked student and staff member present for {{ $date->format('l j F') }}? Already marked rows are left alone." data-confirm-title="Mark remaining present?" data-confirm-action="Mark present" data-confirm-tone="brand">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <x-button variant="brand" icon="check-check">Mark remaining present</x-button>
                    </form>
                @endif
            @endcan

            <form method="GET" class="flex items-center gap-2">
                <label for="date" class="sr-only">Day</label>
                <input id="date" name="date" type="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="field w-44 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
            </form>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Registers taken" :value="$taken.' of '.$arms->count()" icon="clipboard-check" />
        <x-stat-card label="Present" :value="$marked ? round($present / $marked * 100).'%' : '—'" :hint="$present.' of '.$marked.' marked'" icon="user-round-check" tone="naija" />
        <x-stat-card label="Absent" :value="(string) $counts->sum(fn ($row) => $row['absent'] ?? 0)" icon="user-round-x" tone="gold" />
    </section>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3 font-semibold">Class</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Form teacher</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">Present</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">Late</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">Absent</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Register</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($arms as $arm)
                        @php($row = $counts[$arm->id] ?? collect())
                        <tr class="border-t border-line hover:bg-canvas">
                            <td class="px-5 py-3 font-semibold">{{ $arm->displayName() }} <span class="text-sm font-normal text-muted">· {{ $arm->enrolments_count }}</span></td>
                            <td class="px-3 py-3 text-sm text-muted">{{ $arm->formTeacher?->fullName() ?? '—' }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['present'] ?? '—' }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['late'] ?? '—' }}</td>
                            <td @class(['px-3 py-3 text-right', 'font-semibold text-danger' => ($row['absent'] ?? 0) > 0])>{{ $row['absent'] ?? '—' }}</td>
                            <td class="px-3 py-3">
                                @if ($row->isEmpty())
                                    <x-badge tone="gold">Not taken</x-badge>
                                @elseif ($row->sum() < $arm->enrolments_count)
                                    <x-badge tone="sky">{{ $row->sum() }} of {{ $arm->enrolments_count }}</x-badge>
                                @else
                                    <x-badge tone="naija">Taken</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('school.attendance.register', [$arm, 'date' => $date->toDateString()]) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Open <x-lucide-chevron-right class="size-4" aria-hidden="true" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
