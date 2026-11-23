<div class="flex flex-col gap-6">
    <x-card>
        <h2 class="m-0 text-base font-semibold">School details</h2>
        <p class="m-0 mt-1 text-sm text-muted">Fix a name, contact or code for the school. They can also edit most of these themselves.</p>
        <form method="POST" action="{{ route('platform.schools.update', $school) }}" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-6">
            @csrf
            @method('PUT')
            <div class="sm:col-span-6"><x-field label="Name" name="name" bag="details" :value="old('name', $school->name)" required /></div>
            <div class="sm:col-span-4"><x-field label="Web address" name="slug" bag="details" :value="old('slug', $school->slug)" hint="Changing it changes every link to the school, including bookmarks and the admission form." required /></div>
            <div class="sm:col-span-2"><x-field label="School code" name="short_code" bag="details" :value="old('short_code', $school->short_code)" hint="Students use it to sign in." required /></div>
            <div class="sm:col-span-3"><x-select-field label="Type" name="school_type" bag="details" :value="$school->school_type->value" :options="collect($schoolTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()" /></div>
            <div class="sm:col-span-3"><x-select-field label="State" name="state" bag="details" :value="$school->state" placeholder="Choose" :options="collect($states)->mapWithKeys(fn ($state) => [$state => $state])->all()" /></div>
            <div class="sm:col-span-4"><x-field label="Address" name="address" bag="details" :value="old('address', $school->address)" /></div>
            <div class="sm:col-span-2"><x-field label="LGA" name="lga" bag="details" :value="old('lga', $school->lga)" /></div>
            <div class="sm:col-span-2"><x-field label="Phone" name="phone" bag="details" :value="old('phone', $school->phone)" /></div>
            <div class="sm:col-span-2"><x-field label="Email" name="email" type="email" bag="details" :value="old('email', $school->email)" /></div>
            <div class="sm:col-span-2"><x-field label="Website" name="website" type="url" bag="details" :value="old('website', $school->website)" placeholder="https://" /></div>
            <div class="sm:col-span-6"><x-button variant="brand" icon="check">Save details</x-button></div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="m-0 text-base font-semibold">Access</h2>
            <p class="m-0 mt-1 text-sm text-muted">Suspending or archiving stops staff, parents and students signing in. Nothing is deleted.</p>
            <form method="POST" action="{{ route('platform.schools.status', $school) }}" class="mt-4 flex flex-col gap-3" data-confirm="Change {{ $school->name }}’s status? Suspending or archiving signs everyone out of the school." data-confirm-title="Change school status" data-confirm-action="Change status">
                @csrf
                @method('PATCH')
                <x-select-field label="Status" name="status" :value="$school->status->value" :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
                <x-field label="Reason (for the log)" name="reason" placeholder="e.g. Unpaid since September" />
                <x-button variant="secondary" class="self-start">Update status</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="m-0 text-base font-semibold">Trial</h2>
            @if ($school->status === \App\Enums\SchoolStatus::Trial)
                <p class="m-0 mt-1 text-sm text-muted">{{ $school->trial_ends_at ? ($school->trial_ends_at->isPast() ? 'Ended ' : 'Ends ').$school->trial_ends_at->format('j F Y').'.' : 'No end date set.' }} Give them more time to finish setting up.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ([7, 14, 30] as $days)
                        <form method="POST" action="{{ route('platform.schools.trial', $school) }}">
                            @csrf
                            <input type="hidden" name="days" value="{{ $days }}">
                            <x-button variant="soft" size="sm" icon="calendar-plus">+{{ $days }} days</x-button>
                        </form>
                    @endforeach
                </div>
            @else
                <p class="m-0 mt-1 text-sm text-muted">{{ $school->name }} is {{ strtolower($school->status->label()) }}, not on trial.</p>
            @endif
        </x-card>
    </div>
</div>
