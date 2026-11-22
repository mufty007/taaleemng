<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a platform admin who can log in to the platform console', function () {
    $this->artisan('taaleem:admin', ['email' => 'Owner@Taaleem.ng', '--name' => 'Musa Ibrahim', '--password' => 'correct-horse-battery'])
        ->expectsOutputToContain('Created platform admin owner@taaleem.ng.')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'owner@taaleem.ng')->sole();

    expect($admin->is_platform_admin)->toBeTrue()
        ->and($admin->name)->toBe('Musa Ibrahim')
        ->and(Hash::check('correct-horse-battery', $admin->password))->toBeTrue();

    $this->post(route('login.store'), ['login' => 'owner@taaleem.ng', 'password' => 'correct-horse-battery'])->assertRedirect('/app');
    $this->get('/app')->assertRedirect(route('platform.dashboard'));
});

it('generates a strong password when none is given', function () {
    $this->artisan('taaleem:admin', ['email' => 'owner@taaleem.ng'])
        ->expectsOutputToContain('Copy this password now')
        ->assertSuccessful();

    expect(User::query()->where('email', 'owner@taaleem.ng')->sole()->is_platform_admin)->toBeTrue();
});

it('promotes an existing user without changing their password', function () {
    $user = User::factory()->create(['email' => 'bursar@school.ng', 'password' => 'their-own-password']);

    $this->artisan('taaleem:admin', ['email' => 'bursar@school.ng'])
        ->expectsOutputToContain('Updated platform admin bursar@school.ng.')
        ->doesntExpectOutputToContain('Copy this password now')
        ->assertSuccessful();

    expect($user->refresh()->is_platform_admin)->toBeTrue()
        ->and(Hash::check('their-own-password', $user->password))->toBeTrue();
});

it('refuses a bad email or a short password', function () {
    $this->artisan('taaleem:admin', ['email' => 'not-an-email'])->assertFailed();
    $this->artisan('taaleem:admin', ['email' => 'owner@taaleem.ng', '--password' => 'short'])
        ->expectsOutputToContain('Use at least 12 characters')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
