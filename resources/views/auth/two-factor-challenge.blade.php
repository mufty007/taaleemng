<x-layouts.auth title="Two-step verification" heading="Two-step verification">
    <div x-data="{ recovery: false }" class="flex flex-col gap-5">
        <p class="m-0 text-base text-muted" x-show="! recovery">Enter the 6-digit code from your authenticator app.</p>
        <p class="m-0 text-base text-muted" x-show="recovery" x-cloak>Enter one of the recovery codes you saved when you turned on two-step verification.</p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-5">
            @csrf
            <div x-show="! recovery">
                <x-field label="Authentication code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-field label="Recovery code" name="recovery_code" autocomplete="off" x-bind:disabled="! recovery" />
            </div>
            <x-button variant="brand" size="lg" class="w-full">Continue</x-button>
        </form>

        <button type="button" class="self-start text-[15px] font-semibold text-brand-600 hover:text-ink" @click="recovery = ! recovery">
            <span x-show="! recovery">Use a recovery code instead</span>
            <span x-show="recovery" x-cloak>Use an authentication code instead</span>
        </button>
    </div>
</x-layouts.auth>
