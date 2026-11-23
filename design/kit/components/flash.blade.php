@php
    // Fortify flashes machine keys; turn the ones people see into sentences.
    $status = session('status');
    $message = match ($status) {
        'profile-information-updated' => 'Your profile has been saved.',
        'password-updated' => 'Your password has been changed.',
        'two-factor-authentication-enabled' => 'Scan the QR code to finish turning on two-step verification.',
        'two-factor-authentication-confirmed' => 'Two-step verification is on.',
        'two-factor-authentication-disabled' => 'Two-step verification is off.',
        'recovery-codes-generated' => 'New recovery codes created. Save them now.',
        default => $status,
    };
@endphp

@if ($message)
    <div
        data-app-flash
        x-data="{ open: true }"
        x-show="open"
        x-transition.opacity.duration.200ms
        x-init="setTimeout(() => open = false, 6000)"
        role="status"
        class="fixed right-4 bottom-[calc(1rem+var(--tabbar-h,0px))] z-50 flex max-w-sm items-start gap-3 rounded-2xl bg-ink px-4 py-3.5 text-[15px] text-white shadow-float"
    >
        <x-lucide-circle-check class="mt-0.5 size-5 flex-none text-[#7FE0B0]" aria-hidden="true" />
        <span class="flex-1">{{ $message }}</span>
        <button type="button" class="text-ink-300 hover:text-white" @click="open = false" aria-label="Dismiss">
            <x-lucide-x class="size-4" aria-hidden="true" />
        </button>
    </div>
@endif
