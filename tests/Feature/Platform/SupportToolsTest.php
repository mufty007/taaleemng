<?php

use App\Enums\SchoolRole;
use App\Models\User;
use App\Support\Platform\Health;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->create(['name' => 'Musa Support']);
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'name' => 'Greenfield Academy']);
    $this->principal = memberOf($this->school, SchoolRole::Principal, ['name' => 'Funke Adebayo', 'email' => 'funke@greenfield.test', 'phone' => '+2348031234567']);
});

it('finds anyone on the platform by name, email or phone, and shows their schools', function () {
    $find = fn (string $search) => $this->actingAs($this->admin)->get(route('platform.users.index', ['search' => $search]))->assertOk();

    $find('Funke')->assertSee('Funke Adebayo');
    $find('funke@greenfield')->assertSee('Funke Adebayo');
    $find('0803 123 4567')->assertSee('Funke Adebayo');
    $find('nobody-here')->assertSee('No one found');

    $this->actingAs($this->admin)->get(route('platform.users.show', $this->principal))
        ->assertOk()
        ->assertSee('Greenfield Academy')
        ->assertSee('Principal')
        ->assertSee('View as');
});

it('helps someone back in: a reset link, or two-step sign-in turned off', function () {
    Notification::fake();
    $this->principal->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($this->admin)->post(route('platform.users.password-reset', $this->principal))->assertSessionHas('status', 'Password-reset link sent to funke@greenfield.test.');
    Notification::assertSentTo($this->principal, ResetPassword::class);

    $this->actingAs($this->admin)->delete(route('platform.users.two-factor', $this->principal))->assertRedirect();
    expect($this->principal->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and(Activity::query()->where('description', 'turned off two-step sign-in for Funke Adebayo')->exists())->toBeTrue();
});

it('adds and removes super admins, but never the last one or yourself', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post(route('platform.users.admins.store'), ['name' => 'Zainab Yusuf', 'email' => 'Zainab@Taaleem.ng'])->assertRedirect();
    $zainab = User::query()->where('email', 'zainab@taaleem.ng')->sole();
    expect($zainab->is_platform_admin)->toBeTrue();
    Notification::assertSentTo($zainab, ResetPassword::class);

    $this->actingAs($this->admin)->post(route('platform.users.admins.store'), ['name' => 'Funke', 'email' => 'funke@greenfield.test']);
    expect($this->principal->fresh()->is_platform_admin)->toBeTrue()
        ->and(User::query()->where('email', 'funke@greenfield.test')->count())->toBe(1);

    $this->actingAs($this->admin)->delete(route('platform.users.admins.destroy', $this->admin))->assertStatus(422);
    $this->actingAs($this->admin)->delete(route('platform.users.admins.destroy', $zainab))->assertRedirect();
    expect($zainab->fresh()->is_platform_admin)->toBeFalse();

    $this->actingAs($this->principal->fresh())->delete(route('platform.users.admins.destroy', $this->admin))->assertRedirect();
    $this->actingAs($this->admin)->delete(route('platform.users.admins.destroy', $this->principal))->assertStatus(422);
});

it('shows platform health, and retries or deletes failed jobs', function () {
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Notifications\\FeeReminderNotification']), 'exception' => "Swift_TransportException: Connection refused\n#0 trace", 'failed_at' => now()]);

    $this->actingAs($this->admin)->get(route('platform.health'))
        ->assertOk()
        ->assertSee('Scheduler')
        ->assertSee('Has never run')
        ->assertSee('FeeReminderNotification')
        ->assertSee('Connection refused');

    $this->artisan('taaleem:heartbeat')->assertSuccessful();
    expect(Health::scheduler()['status'])->toBe('ok');
    Cache::forever(Health::HEARTBEAT_KEY, now()->subMinutes(20)->toIso8601String());
    expect(Health::scheduler()['status'])->toBe('warn');

    $this->actingAs($this->admin)->delete(route('platform.health.forget'), ['id' => $uuid])->assertRedirect();
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('keeps an audit log of what super admins did', function () {
    $this->actingAs($this->admin)->post(route('platform.view-as.start', [$this->school, $this->principal]));
    $this->post(route('view-as.stop'));

    $this->actingAs($this->admin)->get(route('platform.audit'))
        ->assertOk()
        ->assertSee('viewed Greenfield Academy as Funke Adebayo')
        ->assertSee('Musa Support');

    $this->actingAs($this->admin)->get(route('platform.audit', ['search' => 'trial']))->assertDontSee('viewed Greenfield Academy');
});

it('keeps support tools and health to super admins', function () {
    foreach ([route('platform.users.index'), route('platform.users.show', $this->principal), route('platform.health'), route('platform.audit')] as $url) {
        $this->actingAs($this->principal)->get($url)->assertNotFound();
    }

    $this->actingAs($this->principal)->post(route('platform.users.admins.store'), ['name' => 'Me', 'email' => 'funke@greenfield.test'])->assertNotFound();
    expect($this->principal->fresh()->is_platform_admin)->toBeFalse();
});
