<x-layouts.app title="Add staff">
    <x-page-header kicker="People" title="Add a member of staff" description="They get a login for this school. If they already use Ta’aleem at another school, we link the same account." />

    <form method="POST" action="{{ route('school.staff.store') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        @csrf

        <x-card class="flex flex-col gap-4">
            <h2 class="m-0 text-lg font-semibold">Personal details</h2>
            <div class="grid grid-cols-[96px_minmax(0,1fr)_minmax(0,1fr)] gap-3">
                <x-field label="Title" name="title" placeholder="Mr." />
                <x-field label="First name" name="first_name" required />
                <x-field label="Surname" name="last_name" required />
            </div>
            <x-select-field label="Gender" name="gender" :options="$genderOptions" placeholder="Choose" required />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Email" name="email" type="email" hint="We send a set-password link here." />
                <x-field label="Phone number" name="phone" type="tel" placeholder="0803 123 4567" />
            </div>
            <x-field label="Highest qualification" name="qualification" placeholder="B.Ed Mathematics" />
        </x-card>

        <div class="flex flex-col gap-6">
            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Job</h2>
                <x-field label="Job title" name="designation" placeholder="Mathematics Teacher" required />
                <x-field label="Department" name="department" placeholder="Sciences" />
                <x-select-field label="Employment" name="employment_type" :options="$employmentOptions" value="full_time" required />
                <x-field label="Date employed" name="date_employed" type="date" />
                <x-select-field label="What they can do in Ta’aleem" name="role" :options="$roleOptions" value="teacher" required />
            </x-card>
            @if ($canEditPay)
                <x-card class="flex flex-col gap-4">
                    <h2 class="m-0 text-lg font-semibold">Pay</h2>
                    <p class="m-0 text-sm text-muted">Used when the bursar prepares monthly payroll. Leave blank if they are unpaid or paid outside Ta’aleem.</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-field label="Basic salary (₦)" name="basic_salary" inputmode="decimal" placeholder="180,000" />
                        <x-field label="Allowances (₦)" name="allowances" inputmode="decimal" placeholder="20,000" />
                        <x-field label="Deductions (₦)" name="deductions" inputmode="decimal" placeholder="21,600" />
                    </div>
                    <x-field label="Bank" name="bank_name" placeholder="Access Bank" />
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Account name" name="bank_account_name" />
                        <x-field label="Account number" name="bank_account_number" inputmode="numeric" />
                    </div>
                </x-card>
            @endif
            <div class="flex flex-wrap gap-2">
                <x-button variant="brand" size="lg" icon="check">Add staff member</x-button>
                <x-button variant="ghost" size="lg" :href="route('school.staff.index')">Cancel</x-button>
            </div>
        </div>
    </form>
</x-layouts.app>
