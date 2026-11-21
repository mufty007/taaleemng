<x-layouts.app title="School information">
    <x-page-header kicker="System" title="School information" description="This appears on report cards, receipts, admission letters and the parents’ portal." />

    <form method="POST" action="{{ route('school.profile.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
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
            <x-card x-data="{ preview: null, remove: false }">
                <h2 class="m-0 text-lg font-semibold">School logo</h2>
                <p class="m-0 mt-2 text-[15px] text-muted">Printed on report cards, receipts, invoices and ID cards, next to the Ta’aleem mark. A square PNG with a clear background looks best.</p>
                <div class="mt-4 flex items-center gap-4">
                    <div class="grid size-24 flex-none place-items-center overflow-hidden rounded-2xl border border-line bg-canvas p-2">
                        <template x-if="preview"><img :src="preview" alt="New logo" class="size-full object-contain"></template>
                        <div x-show="! preview && ! remove" class="size-full"><x-school-crest :school="$school" class="size-full text-2xl" /></div>
                        <span x-show="remove && ! preview" x-cloak class="text-xs text-muted">No logo</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-2 self-start rounded-xl border border-line bg-white px-3.5 py-2 text-sm font-semibold hover:border-brand-600">
                            <x-lucide-upload class="size-4" aria-hidden="true" /> {{ $school->logo_path ? 'Change logo' : 'Upload logo' }}
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="sr-only" x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; remove = false">
                        </label>
                        @if ($school->logo_path)
                            <label class="flex items-center gap-2 text-sm text-muted">
                                <input type="checkbox" name="remove_logo" value="1" x-model="remove" class="size-4 rounded border-line"> Remove the logo
                            </label>
                        @endif
                        <span class="text-xs text-muted">PNG, JPG or WebP, up to 2 MB.</span>
                    </div>
                </div>
                @error('logo')<p class="m-0 mt-2 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                <p class="m-0 mt-3 text-sm text-muted">Choose the file, then press <strong>Save school information</strong>.</p>
            </x-card>
        </div>
    </form>
</x-layouts.app>
