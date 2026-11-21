<x-layouts.app title="Enrol a student">
    <x-page-header kicker="People" title="Enrol a student" description="We create an admission number, a login for the student, and a login for the parent (or reuse it if their other child is already here)." />

    @unless ($hasCurrentSession)
        <div class="rounded-2xl border border-gold-200 bg-gold-50 p-5 text-[15px] text-gold">
            <b>Set up the current session first.</b> Students are enrolled into a class for the current session.
            <a href="{{ route('school.sessions.index') }}" class="font-semibold underline">Go to Sessions &amp; Terms</a>
        </div>
    @endunless

    <form method="POST" action="{{ route('school.students.store') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        @csrf

        <x-card class="flex flex-col gap-4">
            <h2 class="m-0 text-lg font-semibold">Student</h2>
            @include('school.students._student-fields', ['student' => null])
        </x-card>

        <div class="flex flex-col gap-6">
            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Class</h2>
                <x-select-field label="Class for this session" name="class_arm_id" :options="$classOptions" placeholder="Choose a class" required />
            </x-card>

            <x-card class="flex flex-col gap-4">
                <div>
                    <h2 class="m-0 text-lg font-semibold">Parent or guardian</h2>
                    <p class="m-0 mt-1 text-sm text-muted">If this phone number already belongs to a parent here, we link this child to them.</p>
                </div>
                <div class="grid grid-cols-[96px_minmax(0,1fr)] gap-3">
                    <x-field label="Title" name="guardian_title" placeholder="Mrs." />
                    <x-field label="First name" name="guardian_first_name" required />
                </div>
                <x-field label="Surname" name="guardian_last_name" required />
                <x-select-field label="Relationship" name="guardian_relationship" :options="$relationshipOptions" required />
                <x-field label="Phone number" name="guardian_phone" type="tel" placeholder="0803 123 4567" hint="Parents log in with this number." required />
                <x-field label="Email" name="guardian_email" type="email" />
                <x-field label="Occupation" name="guardian_occupation" />
            </x-card>

            <div class="flex flex-wrap gap-2">
                <x-button variant="brand" size="lg" icon="check" :disabled="! $hasCurrentSession">Enrol student</x-button>
                <x-button variant="ghost" size="lg" :href="route('school.students.index')">Cancel</x-button>
            </div>
        </div>
    </form>
</x-layouts.app>
