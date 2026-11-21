{{-- The child and parent fields of an application. Expects $levels, $genders and $relationships. Shared by the public form and the office "Record an application" panel. --}}
@php
    $states = collect(config('nigeria.states'))->mapWithKeys(fn ($state) => [$state => $state])->all();
    $religions = ['Islam' => 'Islam', 'Christianity' => 'Christianity', 'Other' => 'Other'];
@endphp

<fieldset class="m-0 flex flex-col gap-4 border-0 p-0">
    <legend class="mb-1 text-base font-semibold">The child</legend>
    <x-select-field label="Class applying for" name="class_level_id" :options="$levels" placeholder="Choose a class" required />
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-field label="First name" name="first_name" required autocomplete="off" />
        <x-field label="Middle name" name="middle_name" autocomplete="off" />
        <x-field label="Surname" name="last_name" required autocomplete="off" />
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-select-field label="Gender" name="gender" :options="$genders" placeholder="Choose" required />
        <x-field label="Date of birth" name="date_of_birth" type="date" required />
        <x-select-field label="Religion" name="religion" :options="$religions" placeholder="Prefer not to say" />
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-select-field label="State of origin" name="state_of_origin" :options="$states" placeholder="Choose a state" />
        <x-field label="Current or last school" name="previous_school" autocomplete="off" />
    </div>
    <x-field label="Home address" name="address" autocomplete="street-address" />
</fieldset>

<fieldset class="m-0 flex flex-col gap-4 border-0 border-t border-line p-0 pt-5">
    <legend class="mb-1 float-left w-full text-base font-semibold">Parent or guardian</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[7rem_minmax(0,1fr)_minmax(0,1fr)]">
        <x-field label="Title" name="guardian_title" placeholder="Mrs" autocomplete="honorific-prefix" />
        <x-field label="First name" name="guardian_first_name" required autocomplete="given-name" />
        <x-field label="Surname" name="guardian_last_name" required autocomplete="family-name" />
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-select-field label="Relationship to the child" name="guardian_relationship" :options="$relationships" placeholder="Choose" required />
        <x-field label="Phone number" name="guardian_phone" type="tel" placeholder="0803 123 4567" required autocomplete="tel" hint="The school will call or text this number." />
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-field label="Email" name="guardian_email" type="email" autocomplete="email" />
        <x-field label="Occupation" name="guardian_occupation" autocomplete="organization-title" />
    </div>
</fieldset>
