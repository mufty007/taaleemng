<x-layouts.auth title="Log in" heading="Welcome back" subheading="Choose how you log in.">
    <div x-data="{ tab: @js(old('school_code') !== null ? 'student' : 'staff') }" class="flex flex-col gap-6">
        <div class="flex gap-1 rounded-[14px] border border-line bg-white p-[5px]" role="tablist" aria-label="Who is logging in">
            <button type="button" role="tab" id="tab-staff" aria-controls="panel-staff" :aria-selected="(tab === 'staff').toString()" @click="tab = 'staff'"
                :class="tab === 'staff' ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                class="flex flex-1 items-center justify-center gap-2 rounded-[10px] px-3 py-2.5 text-sm font-semibold transition">
                <x-lucide-briefcase class="size-4" aria-hidden="true" /> Staff &amp; parents
            </button>
            <button type="button" role="tab" id="tab-student" aria-controls="panel-student" :aria-selected="(tab === 'student').toString()" @click="tab = 'student'"
                :class="tab === 'student' ? 'bg-ink text-white' : 'text-muted hover:text-ink'"
                class="flex flex-1 items-center justify-center gap-2 rounded-[10px] px-3 py-2.5 text-sm font-semibold transition">
                <x-lucide-graduation-cap class="size-4" aria-hidden="true" /> Students
            </button>
        </div>

        {{-- Staff and parents: email or phone --}}
        <form id="panel-staff" role="tabpanel" aria-labelledby="tab-staff" x-show="tab === 'staff'" method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
            @csrf
            <x-field
                label="Email or phone number"
                name="login"
                id="staff-login"
                :value="old('school_code') === null ? old('login') : ''"
                autocomplete="username"
                placeholder="you@school.org or 0803 123 4567"
                required
                autofocus
            />

            <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-between">
                    <label for="staff-password" class="text-sm font-semibold">Password</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-ink">Forgot password?</a>
                </div>
                <div x-data="{ show: false }" class="relative">
                    <input id="staff-password" name="password" :type="show ? 'text' : 'password'" type="password" autocomplete="current-password" required class="field pr-12">
                    <button type="button" @click="show = ! show" class="absolute top-1/2 right-2 grid size-9 -translate-y-1/2 place-items-center rounded-lg text-muted hover:text-ink" :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                        <x-lucide-eye class="size-[18px]" x-show="! show" aria-hidden="true" />
                        <x-lucide-eye-off class="size-[18px]" x-show="show" x-cloak aria-hidden="true" />
                    </button>
                </div>
            </div>

            <label class="flex items-center gap-2.5 text-[15px] text-muted">
                <input type="checkbox" name="remember" class="size-4 rounded accent-brand-600" @checked(old('remember'))>
                Keep me logged in on this device
            </label>

            <x-button variant="brand" size="lg" class="w-full">Log in</x-button>
        </form>

        {{-- Students: school code + admission number --}}
        <form id="panel-student" role="tabpanel" aria-labelledby="tab-student" x-show="tab === 'student'" x-cloak method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
            @csrf
            <div class="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
                <x-field label="School code" name="school_code" id="school-code" placeholder="GFA" autocomplete="organization" class="uppercase" required />
                <x-field label="Admission number" name="login" id="student-login" :value="old('school_code') !== null ? old('login') : ''" placeholder="GFA/2024/0001" autocomplete="username" required />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="student-password" class="text-sm font-semibold">Password</label>
                <input id="student-password" name="password" type="password" autocomplete="current-password" required class="field">
                <p class="text-[13px] text-muted">Your school gave you this password. Forgot it? Ask your form teacher or the school office.</p>
            </div>
            <x-button variant="brand" size="lg" class="w-full">Log in</x-button>
        </form>
    </div>

    <p class="m-0 text-[15px] text-muted">
        New to Ta’aleem? <a href="{{ route('contact', ['kind' => 'demo']) }}" class="font-semibold text-brand-600 hover:text-ink">Book a free demo for your school</a>
    </p>
</x-layouts.auth>
