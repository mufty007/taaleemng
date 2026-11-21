<x-layouts.print :title="'Exam timetable '.$term->name" document="Exam timetable" :school="$currentSchool">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $level?->name ?? 'All classes' }}</div>
        <div class="text-sm text-muted">{{ $term->name }} · {{ $term->academicSession->name }}</div>
    </x-slot:meta>

    <div class="mt-5 flex flex-col gap-5">
        @forelse ($days as $date => $papers)
            @include('school.exams._day', ['print' => true])
        @empty
            <p class="m-0 text-muted">No papers on the timetable yet.</p>
        @endforelse
    </div>

    <p class="m-0 mt-6 text-sm text-muted">Students should be seated ten minutes before each paper with their pens, pencils and admission number. Phones are not allowed in the exam hall.</p>
</x-layouts.print>
