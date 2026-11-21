<x-layouts.app title="School information">
    <x-page-header kicker="System" title="School information" description="This appears on report cards, receipts, admission letters and the parents’ portal." />

    <form method="POST" action="{{ route('school.profile.update') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        @csrf
        @method('PUT')

        <x-card class="flex flex-col gap-4">
            <x-field label="School name" name="name" :value="$school->name" required />
            <x-field label="Motto" name="motto" :value="$school->motto" placeholder="Knowledge and character" />
            <x-field label="Address" name="address" :value="$school->address" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select-field label="State" name="state" :options="$states" :value="$school->state" placeholder="Choose a state" />
                <x-field label="Local government area" name="lga" :value="$school->lga" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Phone" name="phone" type="tel" :value="$school->phone" />
                <x-field label="Email" name="email" type="email" :value="$school->email" />
            </div>
            <x-field label="Website" name="website" type="url" :value="$school->website" placeholder="https://" />
            <x-button variant="brand" class="self-start" icon="check">Save school information</x-button>
        </x-card>

        <div class="flex flex-col gap-6">
            <x-card>
                <h2 class="m-0 text-lg font-semibold">School code</h2>
                <p class="m-0 mt-2 text-[15px] text-muted">Students log in with this code and their admission number.</p>
                <div class="mt-4 inline-block rounded-xl bg-brand-50 px-4 py-2 font-mono text-2xl font-bold tracking-wider text-brand-600">{{ $school->short_code }}</div>
            </x-card>
            <x-card>
                <h2 class="m-0 text-lg font-semibold">Coming next</h2>
                <p class="m-0 mt-2 text-[15px] text-muted">Logo, school crest and the principal’s signature for report cards arrive with Results processing.</p>
            </x-card>
        </div>
    </form>
</x-layouts.app>
