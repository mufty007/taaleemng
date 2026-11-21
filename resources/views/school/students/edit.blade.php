<x-layouts.app :title="'Edit '.$student->fullName()">
    <x-page-header kicker="People" :title="'Edit '.$student->fullName()" :description="'Admission number '.$student->admission_no.'. Class changes happen through promotion at the end of the session.'" />

    <form method="POST" action="{{ route('school.students.update', $student) }}" class="flex max-w-4xl flex-col gap-6">
        @csrf
        @method('PUT')

        <x-card class="flex flex-col gap-4">
            @include('school.students._student-fields', ['student' => $student])
            <x-select-field
                label="Status"
                name="status"
                :value="$student->status->value"
                :options="collect(\App\Enums\StudentStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()"
                required
            />
        </x-card>

        <div class="flex flex-wrap gap-2">
            <x-button variant="brand" size="lg" icon="check">Save changes</x-button>
            <x-button variant="ghost" size="lg" :href="route('school.students.show', $student)">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
