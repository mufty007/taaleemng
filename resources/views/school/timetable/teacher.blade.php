<x-layouts.app :title="'Timetable · '.$staff->fullName()">
    <x-page-header kicker="Timetable" :title="$staff->fullName()" :description="$lessonCount.' '.\Illuminate\Support\Str::plural('lesson', $lessonCount).' a week'.($staff->designation ? ' · '.$staff->designation : '')">
        <x-slot:actions>
            <x-button data-native variant="secondary" icon="printer" :href="route('school.timetable.print', ['kind' => 'teacher', 'ulid' => $staff->ulid])">Print</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('school.timetable._tabs', ['current' => 'teachers'])

    @if ($teachers->isNotEmpty())
        <form method="GET" class="flex items-center gap-2" x-data x-on:change="window.location.assign(@js(route('school.timetable.teacher', '__staff__')).replace('__staff__', $event.target.value))">
            <label for="teacher" class="text-sm font-semibold">Teacher</label>
            <select id="teacher" class="field w-64 bg-white py-2">
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->ulid }}" @selected($teacher->is($staff))>{{ $teacher->fullName() }}</option>
                @endforeach
            </select>
        </form>
    @endif

    <x-card :padding="false" class="p-2">
        @include('school.timetable._week', ['show' => 'teacher'])
    </x-card>
</x-layouts.app>
