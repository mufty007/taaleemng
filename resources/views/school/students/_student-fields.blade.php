{{-- Student details shared by the enrol and edit forms. Expects $student (nullable) and the option arrays. --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-field label="First name" name="first_name" :value="$student?->first_name" required autocomplete="off" />
    <x-field label="Middle name" name="middle_name" :value="$student?->middle_name" autocomplete="off" />
    <x-field label="Surname" name="last_name" :value="$student?->last_name" required autocomplete="off" />
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-select-field label="Gender" name="gender" :options="$genderOptions" :value="$student?->gender->value" placeholder="Choose" required />
    <x-field label="Date of birth" name="date_of_birth" type="date" :value="$student?->date_of_birth?->toDateString()" />
    <x-select-field label="Religion" name="religion" :options="$religions" :value="$student?->religion" placeholder="Prefer not to say" />
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-select-field label="State of origin" name="state_of_origin" :options="$states" :value="$student?->state_of_origin" placeholder="Choose a state" />
    <x-select-field label="Blood group" name="blood_group" :options="$bloodGroups" :value="$student?->blood_group" placeholder="Not known" />
    <x-select-field label="Genotype" name="genotype" :options="$genotypes" :value="$student?->genotype" placeholder="Not known" />
</div>
<x-field label="Home address" name="address" :value="$student?->address" autocomplete="off" />
<x-field label="Previous school" name="previous_school" :value="$student?->previous_school" autocomplete="off" />
<div class="flex flex-col gap-1.5">
    <label for="medical_notes" class="text-sm font-semibold">Medical notes</label>
    <textarea id="medical_notes" name="medical_notes" rows="3" class="field resize-y" placeholder="Allergies, conditions or medication the school should know about">{{ old('medical_notes', $student?->medical_notes) }}</textarea>
    <p class="text-[13px] text-muted">Kept encrypted. Only the admin, principal and bursar can see it.</p>
    @error('medical_notes')
        <p class="text-[13px] font-medium text-danger">{{ $message }}</p>
    @enderror
</div>
