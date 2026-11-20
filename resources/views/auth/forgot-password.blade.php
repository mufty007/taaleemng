<x-layouts.auth title="Forgot password" heading="Reset your password" subheading="Enter the email address on your account and we’ll send you a link to choose a new password.">
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf
        <x-field label="Email address" name="email" type="email" autocomplete="email" required autofocus />
        <x-button variant="brand" size="lg" class="w-full">Email me a reset link</x-button>
    </form>

    <p class="m-0 text-[15px] text-muted">
        Signed up with a phone number only? Ask your school admin to reset your password for you.
    </p>
    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-brand-600 hover:text-ink">
        <x-lucide-arrow-left class="size-4" aria-hidden="true" /> Back to log in
    </a>
</x-layouts.auth>
