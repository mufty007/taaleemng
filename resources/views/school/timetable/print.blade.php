<x-layouts.print :title="$title.' timetable'" document="Timetable" :school="$currentSchool" page="A4 landscape" width="max-w-[1100px]">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $title }}</div>
        <div class="text-sm text-muted">{{ $session ? $session->name.' session' : '' }}</div>
    </x-slot:meta>

    <div class="mt-5">
        @include('school.timetable._week', ['show' => $kind === 'teacher' ? 'teacher' : 'class', 'print' => true])
    </div>
</x-layouts.print>
