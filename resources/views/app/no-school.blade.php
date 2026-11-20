<x-layouts.app title="No school yet">
    <x-card class="mx-auto mt-6 w-full max-w-xl">
        <x-empty-state icon="school" title="You’re not linked to a school yet" body="Ask your school’s admin to invite you, using the email address or phone number on this account.">
            <div class="mt-2 flex flex-wrap justify-center gap-2">
                <x-button variant="secondary" :href="route('account')" icon="user-cog">Check my account details</x-button>
                <x-button variant="soft" :href="route('contact')" icon="message-circle">Contact Ta’aleem</x-button>
            </div>
        </x-empty-state>
    </x-card>
</x-layouts.app>
