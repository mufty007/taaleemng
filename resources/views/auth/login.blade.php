<x-layouts.auth title="Log in" heading="Welcome back" subheading="Log in with the email address or phone number your school registered for you.">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <x-field
            label="Email or phone number"
            name="login"
            autocomplete="username"
            placeholder="you@school.org or 0803 123 4567"
            required
            autofocus
        />

        <div class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between">
                <label for="password" class="text-sm font-semibold">Password</label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-ink">Forgot password?</a>
            </div>
            <div x-data="{ show: false }" class="relative">
                <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" autocomplete="current-password" required class="field pr-12">
                <button type="button" @click="show = ! show" class="absolute top-1/2 right-2 grid size-9 -translate-y-1/2 place-items-center rounded-lg text-muted hover:text-ink" :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                    <x-lucide-eye class="size-[18px]" x-show="! show" aria-hidden="true" />
                    <x-lucide-eye-off class="size-[18px]" x-show="show" x-cloak aria-hidden="true" />
                </button>
            </div>
            @error('password')
                <p class="text-[13px] font-medium text-danger">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2.5 text-[15px] text-muted">
            <input type="checkbox" name="remember" class="size-4 rounded accent-brand-600" @checked(old('remember'))>
            Keep me logged in on this device
        </label>

        <x-button variant="brand" size="lg" class="w-full">Log in</x-button>
    </form>

    <p class="m-0 text-[15px] text-muted">
        New to Ta’aleem? <a href="{{ route('contact', ['kind' => 'demo']) }}" class="font-semibold text-brand-600 hover:text-ink">Book a free demo for your school</a>
    </p>
</x-layouts.auth>
