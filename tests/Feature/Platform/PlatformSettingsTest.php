<?php

use App\Enums\SchoolRole;
use App\Mail\SmtpTestMail;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Platform\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['services.paystack.secret_key' => null, 'services.paystack.public_key' => null, 'services.paystack.base_url' => 'https://api.paystack.test']);
    $this->admin = User::factory()->platformAdmin()->create(['email' => 'owner@taaleem.test']);
    $this->paystackForm = ['public_key' => 'pk_test_abc123', 'secret_key' => 'sk_test_secret9876'];
    $this->mailForm = [
        'host' => 'smtp.zoho.com', 'port' => 465, 'encryption' => 'ssl', 'username' => 'hello@taaleem.ng',
        'password' => 'app-password-1', 'from_address' => 'Hello@Taaleem.ng', 'from_name' => 'Ta’aleem',
    ];
});

it('is for the super admin only, and shows SMS as coming soon', function () {
    $this->actingAs($this->admin)->get(route('platform.settings.edit'))
        ->assertOk()
        ->assertSee('Paystack')
        ->assertSee('Email (SMTP)')
        ->assertSee('Coming soon')
        ->assertSee('data-tab="paystack"', false)
        ->assertSee('data-tab="email"', false)
        ->assertSee(route('webhooks.paystack'));

    $schoolAdmin = memberOf(schoolWithRoles(), SchoolRole::SchoolAdmin);
    $this->actingAs($schoolAdmin)->get(route('platform.settings.edit'))->assertNotFound();
    $this->actingAs($schoolAdmin)->put(route('platform.settings.paystack'), $this->paystackForm)->assertNotFound();
    $this->actingAs($schoolAdmin)->put(route('platform.settings.mail'), $this->mailForm)->assertNotFound();
});

it('saves the Paystack keys encrypted and uses them instead of the server’s', function () {
    $this->actingAs($this->admin)->put(route('platform.settings.paystack'), $this->paystackForm)->assertRedirect(route('platform.settings.edit').'#paystack');

    expect(DB::table('platform_settings')->where('key', 'paystack_secret_key')->value('value'))->not->toContain('sk_test_secret9876');

    PlatformSettings::apply();
    expect(config('services.paystack.secret_key'))->toBe('sk_test_secret9876')
        ->and(config('services.paystack.public_key'))->toBe('pk_test_abc123');

    $this->actingAs($this->admin)->get(route('platform.settings.edit'))
        ->assertSee('Test mode')
        ->assertSee('Saved here')
        ->assertSee('•••• 9876')
        ->assertDontSee('sk_test_secret9876');
});

it('keeps the saved secret when left blank, refuses a mixed test and live pair, and can go back to .env', function () {
    $save = fn (array $form) => $this->actingAs($this->admin)->put(route('platform.settings.paystack'), $form);

    $save(['public_key' => 'pk_test_abc123'])->assertSessionHasErrors('secret_key', errorBag: 'paystack');
    $save(['public_key' => 'pk_test_abc123', 'secret_key' => 'not-a-key'])->assertSessionHasErrors('secret_key', errorBag: 'paystack');
    $save($this->paystackForm)->assertSessionHasNoErrors();
    $save(['public_key' => 'pk_test_new456'])->assertSessionHasNoErrors();
    $save(['public_key' => 'pk_live_abc123'])->assertSessionHasErrors('public_key', errorBag: 'paystack');

    expect(PlatformSettings::get('paystack_secret_key'))->toBe('sk_test_secret9876')
        ->and(PlatformSettings::get('paystack_public_key'))->toBe('pk_test_new456');

    $save(['forget' => 1])->assertSessionHasNoErrors();
    expect(PlatformSetting::query()->count())->toBe(0);
});

it('checks that Paystack accepts the secret key', function () {
    Http::fake(['api.paystack.test/balance' => Http::sequence()
        ->push(['status' => true, 'data' => [['currency' => 'NGN', 'balance' => 1_250_00]]])
        ->push(['status' => false, 'message' => 'Invalid key'], 401)]);
    PlatformSettings::put(['paystack_public_key' => 'pk_test_abc123', 'paystack_secret_key' => 'sk_test_secret9876']);
    PlatformSettings::apply();

    $this->actingAs($this->admin)->post(route('platform.settings.paystack.check'))->assertSessionHas('status', 'Connected to Paystack. Balance: ₦1,250.');
    $this->actingAs($this->admin)->post(route('platform.settings.paystack.check'))->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Paystack did not accept the secret key'));
});

it('sends every school’s email through the one SMTP account', function () {
    $this->actingAs($this->admin)->put(route('platform.settings.mail'), $this->mailForm)->assertRedirect(route('platform.settings.edit').'#email');

    expect(DB::table('platform_settings')->where('key', 'mail_password')->value('value'))->not->toContain('app-password-1');

    PlatformSettings::apply();
    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.zoho.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.password'))->toBe('app-password-1')
        ->and(config('mail.from.address'))->toBe('hello@taaleem.ng');

    // Leaving the password blank keeps the saved one.
    $this->actingAs($this->admin)->put(route('platform.settings.mail'), [...$this->mailForm, 'password' => '', 'port' => 587, 'encryption' => 'tls'])->assertSessionHasNoErrors();
    expect(PlatformSettings::get('mail_password'))->toBe('app-password-1');
});

it('sends a test email so the admin knows the settings work', function () {
    Mail::fake();

    $this->actingAs($this->admin)->post(route('platform.settings.mail.test'), ['to' => 'check@example.test'])
        ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Test email sent to check@example.test'));

    Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $mail) => $mail->hasTo('check@example.test'));
});
