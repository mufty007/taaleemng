@php($expired ??= false)
<x-layouts.auth
    :title="$expired ? 'Link expired' : 'Link already used'"
    :heading="$expired ? 'This link has expired' : 'This link has been used'"
    :subheading="$expired ? 'Sign-in links last '.\App\Notifications\AccountInvitation::DAYS_VALID.' days.' : 'A password has already been set with it.'"
>
    <div class="flex flex-col gap-3">
        <x-button variant="brand" size="lg" class="w-full" :href="route('login')">Go to sign in</x-button>
        <x-button variant="secondary" size="lg" class="w-full" :href="route('password.request')">I don’t know my password</x-button>
    </div>
    <p class="m-0 text-sm text-muted">Still stuck? Ask the school office to send you a new sign-in link.</p>
</x-layouts.auth>
