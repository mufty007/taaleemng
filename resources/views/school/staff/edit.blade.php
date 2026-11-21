<x-layouts.app :title="'Edit '.$staff->fullName()">
    <x-page-header kicker="People" :title="'Edit '.$staff->fullName()" :description="'Staff number '.$staff->staff_no.'. Changing their role updates what they can do in Ta’aleem.'" />

    <form method="POST" action="{{ route('school.staff.update', $staff) }}" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        @csrf
        @method('PUT')

        <x-card class="flex flex-col gap-4">
            <h2 class="m-0 text-lg font-semibold">Personal details</h2>
            <div class="grid grid-cols-[96px_minmax(0,1fr)_minmax(0,1fr)] gap-3">
                <x-field label="Title" name="title" :value="$staff->title" placeholder="Mr." />
                <x-field label="First name" name="first_name" :value="$staff->first_name" required />
                <x-field label="Surname" name="last_name" :value="$staff->last_name" required />
            </div>
            <x-select-field label="Gender" name="gender" :options="$genderOptions" :value="$staff->gender->value" placeholder="Choose" required />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Email" name="email" type="email" :value="$staff->email" hint="Used for sign-in links." />
                <x-field label="Phone number" name="phone" type="tel" :value="$staff->phone" placeholder="0803 123 4567" />
            </div>
            <x-field label="Highest qualification" name="qualification" :value="$staff->qualification" placeholder="B.Ed Mathematics" />
        </x-card>

        <div class="flex flex-col gap-6">
            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Job</h2>
                <x-field label="Job title" name="designation" :value="$staff->designation" placeholder="Mathematics Teacher" required />
                <x-field label="Department" name="department" :value="$staff->department" placeholder="Sciences" />
                <x-select-field label="Employment" name="employment_type" :options="$employmentOptions" :value="$staff->employment_type->value" required />
                <x-field label="Date employed" name="date_employed" type="date" :value="$staff->date_employed?->format('Y-m-d')" />
                <x-select-field label="Status" name="status" :options="$statusOptions" :value="$staff->status->value" required />
                <x-select-field label="What they can do in Ta’aleem" name="role" :options="$roleOptions" :value="$currentRole" required />
            </x-card>
            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Pay</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-field label="Basic salary (₦)" name="basic_salary" inputmode="decimal" :value="old('basic_salary', $staff->basic_salary !== null ? \App\Support\Money::toNaira($staff->basic_salary) : '')" placeholder="180,000" />
                    <x-field label="Allowances (₦)" name="allowances" inputmode="decimal" :value="old('allowances', \App\Support\Money::toNaira($staff->allowances))" placeholder="20,000" />
                    <x-field label="Deductions (₦)" name="deductions" inputmode="decimal" :value="old('deductions', \App\Support\Money::toNaira($staff->deductions))" placeholder="21,600" />
                </div>
                <x-field label="Bank" name="bank_name" :value="$staff->bank_name" placeholder="Access Bank" />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Account name" name="bank_account_name" :value="$staff->bank_account_name" />
                    <x-field label="Account number" name="bank_account_number" :value="$staff->bank_account_number" inputmode="numeric" />
                </div>
            </x-card>
            <div class="flex flex-wrap gap-2">
                <x-button variant="brand" size="lg" icon="check">Save changes</x-button>
                <x-button variant="ghost" size="lg" :href="route('school.staff.show', $staff)">Cancel</x-button>
            </div>
        </div>
    </form>
</x-layouts.app>
