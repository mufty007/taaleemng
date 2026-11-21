<x-layouts.app title="Staff register">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.attendance.index', ['date' => $date->toDateString()]) }}" class="hover:text-ink">Attendance</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Staff</span>
    </div>

    <x-page-header :kicker="$term ? $term->name.' · '.$term->academicSession->name : 'Outside term'" title="Staff register" :description="$date->format('l j F Y').' · Active staff on duty'" />

    <nav class="flex flex-wrap items-center gap-2" aria-label="Choose a day">
        <a href="{{ route('school.attendance.staff', ['date' => $week->first()->copy()->subWeek()->toDateString()]) }}" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-muted hover:text-ink" aria-label="Previous week"><x-lucide-chevron-left class="size-5" aria-hidden="true" /></a>
        @foreach ($week as $day)
            <a href="{{ route('school.attendance.staff', ['date' => $day->toDateString()]) }}" @class([
                'flex min-w-20 flex-col items-center rounded-xl border px-3 py-1.5 text-sm transition',
                'border-ink bg-ink text-white' => $day->isSameDay($date),
                'border-line bg-white text-muted hover:text-ink' => ! $day->isSameDay($date),
                'opacity-50' => $day->isFuture(),
            ]) @if ($day->isSameDay($date)) aria-current="date" @endif>
                <span class="font-semibold">{{ $day->format('D') }}</span>
                <span class="flex items-center gap-1 text-xs">{{ $day->format('j M') }} @if ($marked->has($day->toDateString()))<span class="size-1.5 rounded-full bg-naija" title="Taken"></span>@endif</span>
            </a>
        @endforeach
        <a href="{{ route('school.attendance.staff', ['date' => $week->first()->copy()->addWeek()->toDateString()]) }}" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-muted hover:text-ink" aria-label="Next week"><x-lucide-chevron-right class="size-5" aria-hidden="true" /></a>
    </nav>

    @include('school.attendance._staff-register')
</x-layouts.app>
