<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\SmtpTestMail;
use App\Support\Money;
use App\Support\Payments\Paystack;
use App\Support\Platform\PlatformSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Platform-wide settings for the super admin: the Paystack account schools collect
 * fees through, and the one SMTP account every school's email goes out from.
 * SMS is not available yet.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $secret = (string) config('services.paystack.secret_key');

        return view('platform.settings', [
            'paystack' => [
                'source' => PlatformSettings::source('paystack_secret_key'),
                'mode' => match (true) {
                    str_starts_with($secret, 'sk_live_') => 'live',
                    str_starts_with($secret, 'sk_test_') => 'test',
                    default => null,
                },
                'publicKey' => PlatformSettings::get('paystack_public_key') ?? config('services.paystack.public_key'),
                'secretMask' => PlatformSettings::mask($secret),
                'requiresSubaccount' => app(Paystack::class)->requiresSubaccount(),
            ],
            'mail' => [
                'source' => PlatformSettings::source('mail_host'),
                'host' => PlatformSettings::get('mail_host'),
                'port' => PlatformSettings::get('mail_port') ?? '587',
                'encryption' => PlatformSettings::get('mail_encryption') ?? 'tls',
                'username' => PlatformSettings::get('mail_username'),
                'passwordMask' => PlatformSettings::mask(PlatformSettings::get('mail_password')),
                'fromAddress' => PlatformSettings::get('mail_from_address') ?? config('mail.from.address'),
                'fromName' => PlatformSettings::get('mail_from_name') ?? config('mail.from.name'),
                'mailer' => config('mail.default'),
            ],
            'webhookUrl' => route('webhooks.paystack'),
            'testRecipient' => $request->user()->email,
        ]);
    }

    public function updatePaystack(Request $request): RedirectResponse
    {
        if ($request->boolean('forget')) {
            PlatformSettings::put(['paystack_public_key' => null, 'paystack_secret_key' => null]);
            activity('platform')->log('removed the saved Paystack keys');

            return redirect()->to(route('platform.settings.edit').'#paystack')->with('status', 'Saved Paystack keys removed. The server’s .env keys apply again, if it has any.');
        }

        $data = $request->validateWithBag('paystack', [
            'public_key' => ['required', 'string', 'max:120', 'regex:/^pk_(test|live)_[A-Za-z0-9]+$/'],
            'secret_key' => [Rule::requiredIf(blank(PlatformSettings::get('paystack_secret_key'))), 'nullable', 'string', 'max:120', 'regex:/^sk_(test|live)_[A-Za-z0-9]+$/'],
        ], [
            'public_key.regex' => 'A Paystack public key starts with pk_test_ or pk_live_.',
            'secret_key.regex' => 'A Paystack secret key starts with sk_test_ or sk_live_.',
            'secret_key.required' => 'Enter the secret key from Paystack: Settings > API Keys & Webhooks.',
        ]);

        $secret = $data['secret_key'] ?? PlatformSettings::get('paystack_secret_key');

        if (Str::contains($data['public_key'], '_live_') !== Str::contains((string) $secret, '_live_')) {
            return back()->withInput()->withErrors(['public_key' => 'Use the test pair or the live pair of keys, not one of each.'], 'paystack');
        }

        PlatformSettings::put(['paystack_public_key' => $data['public_key'], 'paystack_secret_key' => $secret]);
        activity('platform')->log('updated the Paystack keys ('.(Str::contains((string) $secret, '_live_') ? 'live' : 'test').' mode)');

        return redirect()->to(route('platform.settings.edit').'#paystack')->with('status', 'Paystack keys saved. Use “Check connection” to confirm Paystack accepts them.');
    }

    /**
     * Ask Paystack for the account balance: proves the secret key works.
     */
    public function testPaystack(Paystack $paystack): RedirectResponse
    {
        if (! $paystack->isConfigured()) {
            return back()->with('status', 'Add the Paystack keys first.');
        }

        try {
            $balances = $paystack->balance();
        } catch (Throwable) {
            return back()->with('status', 'Paystack did not accept the secret key, or could not be reached. Check the key and the server’s internet connection.');
        }

        $naira = collect($balances)->firstWhere('currency', 'NGN');

        return back()->with('status', 'Connected to Paystack. Balance: '.Money::format((int) ($naira['balance'] ?? 0)).'.');
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('mail', [
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:100'],
        ]);

        PlatformSettings::put([
            'mail_host' => trim($data['host']),
            'mail_port' => (string) $data['port'],
            'mail_encryption' => $data['encryption'],
            'mail_username' => $data['username'] ?? null,
            'mail_password' => filled($data['password'] ?? null) ? $data['password'] : PlatformSettings::get('mail_password'),
            'mail_from_address' => Str::lower($data['from_address']),
            'mail_from_name' => $data['from_name'],
        ]);

        // Queue workers read mail settings when they start; restart them so queued email uses the new account.
        Artisan::call('queue:restart');
        activity('platform')->log('updated the email (SMTP) settings');

        return redirect()->to(route('platform.settings.edit').'#email')->with('status', 'Email settings saved. Send a test email to check them.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('mailTest', ['to' => ['required', 'email', 'max:190']]);

        try {
            Mail::to($data['to'])->send(new SmtpTestMail($request->user()->name));
        } catch (Throwable $exception) {
            return back()->withInput()->with('status', 'The email could not be sent: '.Str::limit($exception->getMessage(), 180));
        }

        return back()->with('status', "Test email sent to {$data['to']}. If it doesn’t arrive in a few minutes, check the spam folder and the SMTP details.");
    }
}
