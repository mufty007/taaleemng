<x-layouts.auth title="Confirm password" heading="Confirm your password" subheading="This is a protected area. Please confirm your password to continue.">
    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf
        <x-field label="Password" name="password" type="password" autocomplete="current-password" required autofocus />
        <x-button variant="brand" size="lg" class="w-full">Confirm</x-button>
    </form>
</x-layouts.auth>
