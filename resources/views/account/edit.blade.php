<x-layouts.app title="Account & security">
    <x-page-header kicker="Your account" title="Account & security" description="These details are yours, not your school’s. They apply in every school you belong to." />

    @php($accountTab = $errors->updatePassword->isNotEmpty() ? 'password' : (str_contains((string) session('status'), 'two-factor') || str_contains((string) session('status'), 'recovery') || $errors->confirmTwoFactorAuthentication->isNotEmpty() ? 'security' : 'profile'))
    <x-tabs :tabs="['profile' => 'Profile', 'password' => 'Password', 'security' => 'Two-step verification']" :default="$accountTab" label="Account settings" class="max-w-3xl">
        <x-tab name="profile">
        <x-card>
            <h2 class="m-0 text-lg font-semibold">Profile</h2>
            <p class="m-0 mt-1 mb-5 text-sm text-muted">You can log in with either your email address or your phone number.</p>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <x-field label="Full name" name="name" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
                <x-field label="Email address" name="email" type="email" :value="$user->email" bag="updateProfileInformation" autocomplete="email" />
                <x-field label="Phone number" name="phone" type="tel" :value="$user->phone" bag="updateProfileInformation" autocomplete="tel" hint="Nigerian mobile number, e.g. 0803 123 4567." />
                <x-button class="self-start">Save profile</x-button>
            </form>
        </x-card>
        </x-tab>

        <x-tab name="password">
        <x-card>
            <h2 class="m-0 text-lg font-semibold">Password</h2>
            <p class="m-0 mt-1 mb-5 text-sm text-muted">Use at least 8 characters. Don’t reuse a password from another website.</p>
            <form method="POST" action="{{ route('user-password.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <x-field label="Current password" name="current_password" type="password" bag="updatePassword" autocomplete="current-password" required />
                <x-field label="New password" name="password" type="password" bag="updatePassword" autocomplete="new-password" required />
                <x-field label="Confirm new password" name="password_confirmation" type="password" bag="updatePassword" autocomplete="new-password" required />
                <x-button class="self-start">Change password</x-button>
            </form>
        </x-card>
        </x-tab>

        <x-tab name="security">
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span class="grid size-11 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600">
                        <x-lucide-smartphone class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="m-0 text-lg font-semibold">Two-step verification</h2>
                        <p class="m-0 mt-1 max-w-xl text-sm text-muted">When you log in, you’ll also enter a code from an authenticator app such as Google Authenticator or Microsoft Authenticator. Recommended for admins, principals and bursars.</p>
                    </div>
                </div>
                @if ($user->hasEnabledTwoFactorAuthentication())
                    <x-badge tone="naija"><x-lucide-shield-check class="size-3.5" aria-hidden="true" /> On</x-badge>
                @else
                    <x-badge>Off</x-badge>
                @endif
            </div>

            <div class="mt-6">
                @if (! $user->two_factor_secret)
                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <x-button icon="key-round">Turn on two-step verification</x-button>
                    </form>
                @elseif (! $user->two_factor_confirmed_at)
                    <div class="grid grid-cols-1 items-start gap-6 rounded-2xl bg-canvas p-5 md:grid-cols-[auto_minmax(0,1fr)]">
                        <div class="rounded-xl bg-white p-3 ring-1 ring-line [&_svg]:size-44">{!! $user->twoFactorQrCodeSvg() !!}</div>
                        <div class="flex flex-col gap-4">
                            <ol class="m-0 flex flex-col gap-1.5 pl-5 text-[15px] text-muted">
                                <li>Open your authenticator app and scan the QR code.</li>
                                <li>Can’t scan? Enter this key instead: <code class="rounded bg-white px-1.5 py-0.5 font-mono text-sm text-ink ring-1 ring-line">{{ decrypt($user->two_factor_secret) }}</code></li>
                                <li>Type the 6-digit code the app shows.</li>
                            </ol>
                            <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <div class="w-48">
                                    <x-field label="6-digit code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" bag="confirmTwoFactorAuthentication" required />
                                </div>
                                <x-button variant="brand">Confirm and turn on</x-button>
                            </form>
                            <form method="POST" action="{{ route('two-factor.disable') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-muted hover:text-ink">Cancel setup</button>
                            </form>
                        </div>
                    </div>
                @else
                    @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                        <div class="mb-5 rounded-2xl border border-gold-200 bg-gold-50 p-5">
                            <h3 class="m-0 text-base font-semibold text-gold">Save these recovery codes</h3>
                            <p class="m-0 mt-1 mb-4 text-sm text-gold">If you lose your phone, each code lets you log in once. Print them or keep them somewhere safe. They won’t be shown again.</p>
                            <ul class="m-0 grid list-none grid-cols-2 gap-2 p-0 font-mono text-sm sm:grid-cols-4">
                                @foreach ($user->recoveryCodes() as $code)
                                    <li class="rounded-lg bg-white px-3 py-2 text-ink ring-1 ring-gold-200">{{ $code }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                            @csrf
                            <x-button variant="secondary" icon="key-round">New recovery codes</x-button>
                        </form>
                        <form method="POST" action="{{ route('two-factor.disable') }}">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" class="text-danger hover:bg-danger-50 hover:text-danger">Turn off</x-button>
                        </form>
                    </div>
                @endif
            </div>
        </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
