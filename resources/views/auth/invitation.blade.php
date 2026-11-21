<x-layouts.auth
    title="Choose your password"
    :heading="'Welcome, '.str($user->name)->before(' ')"
    :subheading="($school ? $school->name.' has set up your account. ' : '').'Choose a password to finish. Use at least 8 characters; a short sentence is easier to remember than a random word.'"
>
    <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col gap-5">
        @csrf
        <div class="rounded-xl bg-canvas px-4 py-3 text-sm">
            <span class="text-muted">You’ll sign in with</span>
            <span class="font-semibold">{{ $user->email }}</span>@if ($user->phone)<span class="text-muted"> or </span><span class="font-semibold">{{ $user->phone }}</span>@endif
        </div>

        <x-field label="Password" name="password" type="password" autocomplete="new-password" required autofocus />
        <x-field label="Confirm password" name="password_confirmation" type="password" autocomplete="new-password" required />

        <x-button variant="brand" size="lg" class="w-full">Save password and sign in</x-button>
    </form>
</x-layouts.auth>
