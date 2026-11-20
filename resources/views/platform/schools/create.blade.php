<x-layouts.app title="New school">
    <x-page-header kicker="Platform console" title="Set up a new school" description="The school starts on a {{ config('taaleem.trial_days') }}-day trial with the standard roles. Its admin gets an email to choose a password." />

    <form method="POST" action="{{ route('platform.schools.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        @csrf

        <x-card class="flex flex-col gap-4">
            <h2 class="m-0 text-lg font-semibold">School</h2>
            <x-field label="School name" name="name" placeholder="Greenfield Academy, Abuja" required autofocus />
            <x-select-field
                label="Type of school"
                name="school_type"
                :options="collect($schoolTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()"
                placeholder="Choose one"
                required
            />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select-field label="State" name="state" :options="array_combine($states, $states)" placeholder="Choose a state" />
                <x-field label="School phone" name="phone" type="tel" placeholder="0803 123 4567" />
            </div>
            <x-field label="School email" name="email" type="email" placeholder="info@school.org" />
        </x-card>

        <div class="flex flex-col gap-6">
            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">First school admin</h2>
                <p class="m-0 -mt-2 text-sm text-muted">Usually the proprietor or the school’s IT person. They can invite everyone else.</p>
                <x-field label="Full name" name="admin_name" autocomplete="off" required />
                <x-field label="Email address" name="admin_email" type="email" autocomplete="off" required />
            </x-card>
            <div class="flex flex-wrap gap-2">
                <x-button variant="brand" size="lg" icon="check">Create school</x-button>
                <x-button variant="ghost" size="lg" :href="route('platform.schools.index')">Cancel</x-button>
            </div>
        </div>
    </form>
</x-layouts.app>
