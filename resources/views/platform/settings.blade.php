<x-layouts.app title="Platform settings">
    <x-page-header kicker="Platform console" title="Settings" description="Accounts every school on Ta’aleem shares: Paystack for school fees, and one email account for invitations, password resets and fee reminders." />

    @php
        $sourceLabel = fn (string $source): string => match ($source) {
            'saved' => 'Saved here',
            'env' => 'From the server’s .env',
            default => 'Not set',
        };
        $settingsTab = match (true) {
            $errors->mail->isNotEmpty() || $errors->mailTest->isNotEmpty() => 'email',
            $errors->paystack->isNotEmpty() => 'paystack',
            default => 'paystack',
        };
    @endphp

    <form id="paystack-check" method="POST" action="{{ route('platform.settings.paystack.check') }}" class="hidden">@csrf</form>
    <form id="paystack-forget" method="POST" action="{{ route('platform.settings.paystack') }}" class="hidden" data-confirm="Remove the saved Paystack keys? The server’s .env keys apply again; without them, parents can’t pay online." data-confirm-title="Remove Paystack keys" data-confirm-action="Remove keys">
        @csrf
        @method('PUT')
        <input type="hidden" name="forget" value="1">
    </form>

    <x-tabs :tabs="['paystack' => 'Paystack', 'email' => 'Email', 'sms' => 'SMS', 'about' => 'About']" :default="$settingsTab" label="Platform settings">
        <x-tab name="paystack">
            <x-card :padding="false" class="overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 flex-none place-items-center rounded-xl bg-sky-50 text-sky"><x-lucide-credit-card class="size-5" aria-hidden="true" /></span>
                        <div>
                            <h2 class="m-0 text-lg font-semibold">Paystack</h2>
                            <p class="m-0 text-sm text-muted">Parents pay school fees by card, transfer or USSD. Each school’s money settles to its own payout account.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($paystack['mode'] === 'live')
                            <x-badge tone="naija">Live mode</x-badge>
                        @elseif ($paystack['mode'] === 'test')
                            <x-badge tone="gold">Test mode</x-badge>
                        @endif
                        <x-badge>{{ $sourceLabel($paystack['source']) }}</x-badge>
                    </div>
                </div>

                <form method="POST" action="{{ route('platform.settings.paystack') }}" class="flex flex-col gap-4 p-5 sm:p-6">
                    @csrf
                    @method('PUT')
                    <x-field label="Public key" name="public_key" bag="paystack" :value="old('public_key', $paystack['publicKey'])" placeholder="pk_test_…" autocomplete="off" spellcheck="false" required />
                    <x-field
                        label="Secret key"
                        name="secret_key"
                        type="password"
                        bag="paystack"
                        :placeholder="match ($paystack['source']) { 'saved' => 'Saved ('.$paystack['secretMask'].'). Leave blank to keep it.', 'env' => 'Using the server’s key ('.$paystack['secretMask'].'). Enter a key to save one here.', default => 'sk_test_…' }"
                        hint="From the Paystack dashboard: Settings, API Keys & Webhooks. Stored encrypted and never shown again."
                        autocomplete="new-password"
                    />
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button variant="brand" icon="check">Save keys</x-button>
                        <x-button type="submit" variant="secondary" icon="plug-zap" form="paystack-check">Check connection</x-button>
                        @if ($paystack['source'] === 'saved')
                            <x-button type="submit" variant="ghost" form="paystack-forget">Remove saved keys</x-button>
                        @endif
                    </div>
                </form>

                <dl class="m-0 grid grid-cols-1 gap-4 border-t border-line bg-canvas px-5 py-4 text-sm sm:px-6">
                    <div x-data="{ copied: false }">
                        <dt class="font-semibold">Webhook URL</dt>
                        <dd class="m-0 mt-1 flex flex-wrap items-center gap-2">
                            <code class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 font-mono text-xs break-all ring-1 ring-line">{{ $webhookUrl }}</code>
                            <button type="button" class="text-xs font-semibold text-brand-600 hover:text-ink" x-on:click="navigator.clipboard.writeText(@js($webhookUrl)); copied = true; setTimeout(() => copied = false, 2000)" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                        </dd>
                        <p class="m-0 mt-1 text-muted">Paste it into Paystack (Settings, API Keys &amp; Webhooks) so payments confirm even if a parent closes the page. Paystack can’t reach a computer that isn’t on the internet, so on a laptop payments confirm when the parent returns to the site instead.</p>
                    </div>
                    @if (! $paystack['requiresSubaccount'])
                        <div class="flex items-start gap-2 rounded-xl bg-gold-50 px-3 py-2.5 text-ink">
                            <x-lucide-flask-conical class="mt-0.5 size-4 flex-none text-gold" aria-hidden="true" />
                            <span>Testing setup: schools without a payout account can take payments, and the money settles to this platform account. Production always needs each school’s own payout account.</span>
                        </div>
                    @endif
                    @if ($paystack['mode'] === 'test')
                        <div>
                            <dt class="font-semibold">Test card</dt>
                            <dd class="m-0 mt-1 text-muted"><span class="font-mono text-ink">4084 0840 8408 4081</span> · any future expiry · CVV <span class="font-mono text-ink">408</span> · PIN <span class="font-mono text-ink">0000</span> · OTP <span class="font-mono text-ink">123456</span>. No real money moves in test mode.</dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </x-tab>

        <x-tab name="email">
            <x-card :padding="false" class="overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600"><x-lucide-mail class="size-5" aria-hidden="true" /></span>
                        <div>
                            <h2 class="m-0 text-lg font-semibold">Email (SMTP)</h2>
                            <p class="m-0 text-sm text-muted">One account sends every school’s email. Use the SMTP details from your mail provider (Zoho, Brevo, Mailgun, Google Workspace…).</p>
                        </div>
                    </div>
                    @if ($mail['source'] === 'none')
                        <x-badge tone="gold">{{ $mail['mailer'] === 'log' ? 'Not sending: written to the log' : 'Not set' }}</x-badge>
                    @else
                        <x-badge tone="naija">{{ $sourceLabel($mail['source']) }}</x-badge>
                    @endif
                </div>

                <form method="POST" action="{{ route('platform.settings.mail') }}" class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-6 sm:p-6">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-4"><x-field label="SMTP server" name="host" bag="mail" :value="old('host', $mail['host'])" placeholder="smtp.zoho.com" autocomplete="off" required /></div>
                    <div class="sm:col-span-2"><x-field label="Port" name="port" type="number" bag="mail" :value="old('port', $mail['port'])" required /></div>
                    <div class="sm:col-span-6"><x-select-field label="Security" name="encryption" bag="mail" :value="$mail['encryption']" :options="['tls' => 'TLS (STARTTLS, usually port 587)', 'ssl' => 'SSL (usually port 465)']" /></div>
                    <div class="sm:col-span-3"><x-field label="Username" name="username" bag="mail" :value="old('username', $mail['username'])" autocomplete="off" /></div>
                    <div class="sm:col-span-3"><x-field label="Password" name="password" type="password" bag="mail" :placeholder="$mail['passwordMask'] ? 'Saved. Leave blank to keep it.' : ''" autocomplete="new-password" /></div>
                    <div class="sm:col-span-3"><x-field label="Send from (email)" name="from_address" type="email" bag="mail" :value="old('from_address', $mail['fromAddress'])" placeholder="hello@taaleem.ng" required /></div>
                    <div class="sm:col-span-3"><x-field label="Send from (name)" name="from_name" bag="mail" :value="old('from_name', $mail['fromName'])" placeholder="Ta’aleem" required /></div>
                    <p class="m-0 text-sm text-muted sm:col-span-6">Fee reminders show the school’s name and use the school’s email for replies, so parents answer the school, not Ta’aleem.</p>
                    <div class="sm:col-span-6"><x-button variant="brand" icon="check">Save email settings</x-button></div>
                </form>

                <form method="POST" action="{{ route('platform.settings.mail.test') }}" class="flex flex-wrap items-end gap-3 border-t border-line bg-canvas px-5 py-4 sm:px-6">
                    @csrf
                    <div class="min-w-[220px] flex-1"><x-field label="Send a test email to" name="to" type="email" bag="mailTest" :value="old('to', $testRecipient)" required /></div>
                    <x-button variant="secondary" icon="send">Send test</x-button>
                </form>
            </x-card>
        </x-tab>

        <x-tab name="sms">
            <x-card class="max-w-xl">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid size-10 flex-none place-items-center rounded-xl bg-chip text-muted"><x-lucide-message-square-text class="size-5" aria-hidden="true" /></span>
                    <x-badge tone="brand">Coming soon</x-badge>
                </div>
                <h2 class="m-0 mt-3 text-lg font-semibold">SMS</h2>
                <p class="m-0 mt-1 text-sm text-muted">Text message reminders and alerts will be set up here. Until then, schools reach parents by email and WhatsApp.</p>
            </x-card>
        </x-tab>

        <x-tab name="about">
            <x-card class="max-w-xl">
                <h2 class="m-0 text-base font-semibold">How these settings work</h2>
                <ul class="m-0 mt-3 flex list-none flex-col gap-2.5 p-0 text-sm text-muted">
                    <li class="flex gap-2"><x-lucide-lock class="mt-0.5 size-4 flex-none text-naija" aria-hidden="true" /> Secret keys and passwords are encrypted and never shown again.</li>
                    <li class="flex gap-2"><x-lucide-layers class="mt-0.5 size-4 flex-none text-brand-600" aria-hidden="true" /> Anything saved here replaces the server’s .env value; clear it to fall back.</li>
                    <li class="flex gap-2"><x-lucide-refresh-cw class="mt-0.5 size-4 flex-none text-sky" aria-hidden="true" /> Saving email settings restarts the background workers so queued emails use them.</li>
                </ul>
            </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
