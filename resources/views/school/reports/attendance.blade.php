<x-layouts.app title="Attendance report">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.reports.index') }}" class="hover:text-ink">Reports</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Attendance</span>
    </div>

    <x-page-header kicker="Reports" title="Attendance summary" :description="$term->name.' · '.$term->academicSession->name">
        <x-slot:actions>
            <form method="GET">
                <label for="term" class="sr-only">Term</label>
                <select id="term" name="term" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->academicSession->name }}</option>
                    @endforeach
                </select>
            </form>
            <x-button variant="ghost" icon="clipboard-check" :href="route('school.attendance.reports', ['term' => $term->id])">Full attendance report</x-button>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Student presence" :value="$students['rate'] !== null ? $students['rate'].'%' : '—'" icon="user-round-check" tone="naija" />
        <x-stat-card label="Student absences" :value="(string) $students['absent']" icon="user-round-x" tone="gold" />
        <x-stat-card label="Staff presence" :value="$staffSummary['rate'] !== null ? $staffSummary['rate'].'%' : '—'" icon="briefcase" tone="sky" />
    </section>

    <x-card :padding="false">
        <div class="border-b border-line px-5 py-4">
            <h2 class="m-0 text-base font-semibold">Chronic absentees</h2>
            <p class="m-0 text-sm text-muted">Below 80% this term (top 15)</p>
        </div>
        @if ($absentees->isEmpty())
            <x-empty-state icon="user-round-check" title="No chronic absentees" />
        @else
            <ul class="m-0 list-none divide-y divide-line p-0">
                @foreach ($absentees as $row)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-[15px]">
                        <span><span class="font-semibold">{{ $row['student'] }}</span> <span class="text-muted">· {{ $row['class'] }}</span></span>
                        <span class="font-semibold text-danger">{{ $row['rate'] }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-layouts.app>
