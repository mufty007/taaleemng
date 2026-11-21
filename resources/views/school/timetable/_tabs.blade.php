{{-- Links between the timetable pages. Expects $current: classes, teachers or periods. --}}
@php
    $canManage = auth()->user()->can('timetable.manage');
    $teacherPage = $canManage
        ? \App\Models\Staff::query()->whereHas('classSubjects')->orderBy('last_name')->first()
        : auth()->user()->staffRecord();
    $links = array_filter([
        'classes' => ['Class timetables', route('school.timetable.index', ['class' => request('class')])],
        'teachers' => $teacherPage ? [$canManage ? 'Teachers' : 'My timetable', route('school.timetable.teacher', $teacherPage)] : null,
        'periods' => $canManage ? ['Bell schedule', route('school.timetable.periods')] : null,
    ]);
@endphp
<nav class="-mx-1 overflow-x-auto px-1" aria-label="Timetable">
    <div class="inline-flex gap-1 rounded-2xl border border-line bg-white p-1.5">
        @foreach ($links as $key => [$label, $url])
            <a href="{{ $url }}" @class(['rounded-xl px-4 py-2 text-sm font-semibold whitespace-nowrap transition', 'bg-ink text-white' => $current === $key, 'text-muted hover:text-ink' => $current !== $key]) @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </div>
</nav>
