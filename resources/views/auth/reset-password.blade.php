<x-layouts.auth title="Choose a password" heading="Choose a new password" subheading="Use at least 8 characters. A short sentence is easier to remember than a random word.">
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field label="Email address" name="email" type="email" :value="$request->email" autocomplete="email" required />
        <x-field label="New password" name="password" type="password" autocomplete="new-password" required autofocus />
        <x-field label="Confirm new password" name="password_confirmation" type="password" autocomplete="new-password" required />

        <x-button variant="brand" size="lg" class="w-full">Save password and log in</x-button>
    </form>
</x-layouts.auth>
