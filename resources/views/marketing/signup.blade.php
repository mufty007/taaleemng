<x-layouts.marketing title="Start your school">
    <section class="mx-auto max-w-[920px] px-4 pt-14 pb-20 sm:px-6 sm:pt-[72px]">
        <div class="mb-10 flex flex-col gap-3 text-center" data-enter>
            <div class="kicker">Start free trial</div>
            <h1 class="m-0 text-[clamp(36px,5vw,56px)] leading-[1.05] font-bold tracking-[-0.04em]">Create your school on Ta’aleem</h1>
            <p class="m-0 text-[17px] text-muted">{{ config('taaleem.trial_days') }} days free. Every plan includes all features — pick seats that fit your school, then choose flat fee or per-student billing.</p>
        </div>

        <form method="POST" action="{{ route('signup.store') }}" class="flex flex-col gap-6 rounded-[28px] border border-line bg-white p-6 sm:p-8" data-enter>
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="School name" name="school_name" :value="old('school_name')" required placeholder="Greenfield Academy" />
                <x-select-field label="School type" name="school_type" :options="$schoolTypes" :value="old('school_type')" placeholder="Choose type" required />
                <x-field label="State" name="state" :value="old('state')" placeholder="FCT" />
                <x-select-field label="Class structure" name="class_structure" :options="$classStructures" :value="old('class_structure', 'arms')" required />
            </div>

            <fieldset class="m-0 rounded-2xl border border-line p-4">
                <legend class="px-1 text-sm font-semibold">Plan</legend>
                <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-3">
                    @foreach ($plans as $plan)
                        <label @class(['flex cursor-pointer flex-col gap-2 rounded-2xl border p-4', 'border-brand bg-brand-50' => old('plan', 'standard') === $plan['key'], 'border-line hover:border-brand' => old('plan', 'standard') !== $plan['key']])>
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ $plan['name'] }}</span>
                                <input type="radio" name="plan" value="{{ $plan['key'] }}" @checked(old('plan', 'standard') === $plan['key']) class="size-4 border-line text-brand-600">
                            </span>
                            <span class="text-sm text-muted">{{ number_format($plan['max_students']) }} students · {{ number_format($plan['max_staff']) }} staff</span>
                            <span class="text-sm font-semibold">{{ $plan['flat_termly'] ?? '—' }} / term</span>
                            <span class="text-xs text-muted">or {{ $plan['per_student'] ?? '—' }} / student</span>
                        </label>
                    @endforeach
                </div>
                @error('plan')<p class="m-0 mt-2 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
            </fieldset>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select-field label="How you want to pay" name="billing_model" :options="$billingModels" :value="old('billing_model', 'flat')" required />
                <x-select-field label="Billing cycle" name="billing_cycle" :options="['termly' => 'Every term', 'annual' => 'Once a year']" :value="old('billing_cycle', 'termly')" required />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Your name" name="admin_name" :value="old('admin_name')" required />
                <x-field label="Work email" name="admin_email" type="email" :value="old('admin_email')" required />
                <x-field label="Password" name="password" type="password" required autocomplete="new-password" />
                <x-field label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
            </div>

            <x-button variant="brand" size="lg" icon="arrow-right" class="self-start">Start free trial</x-button>
            <p class="m-0 text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-ink">Sign in</a>.</p>
        </form>
    </section>
</x-layouts.marketing>
