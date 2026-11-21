<?php

use App\Enums\SchoolRole;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->school = schoolWithRoles(['slug' => 'greenfield']);
    $this->admin = memberOf($this->school);
    $this->teacher = memberOf($this->school, SchoolRole::Teacher, ['name' => 'Emeka Okonkwo', 'email' => 'emeka@greenfield.test', 'phone' => '+2348031112222']);
});

it('emails a member a link to choose their own password', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post(route('school.members.invite', [$this->school, $this->teacher->ulid]))
        ->assertSessionHas('status', 'A sign-in link is on its way to emeka@greenfield.test. It works once and expires in 7 days.');

    Notification::assertSentTo($this->teacher, AccountInvitation::class, function (AccountInvitation $invitation) {
        $mail = $invitation->toMail($this->teacher);

        return $invitation->school->is($this->school)
            && $mail->subject === "{$this->school->name} has set up your Ta’aleem account"
            && str_contains($mail->actionUrl, '/invitation/'.$this->teacher->ulid);
    });
});

it('lets the invited member set a password and signs them straight in', function () {
    $url = AccountInvitation::url($this->teacher, $this->school);

    $this->get($url)->assertOk()->assertSee('Welcome, Emeka')->assertSee('emeka@greenfield.test');

    $this->post($url, ['password' => 'a long new password', 'password_confirmation' => 'a long new password'])
        ->assertRedirect(route('school.dashboard', $this->school));

    $this->assertAuthenticatedAs($this->teacher);
    expect(Hash::check('a long new password', $this->teacher->fresh()->password))->toBeTrue()
        ->and($this->teacher->fresh()->email_verified_at)->not->toBeNull();
});

it('only works once', function () {
    $url = AccountInvitation::url($this->teacher, $this->school);
    $this->post($url, ['password' => 'a long new password', 'password_confirmation' => 'a long new password']);
    auth()->logout();

    $this->get($url)->assertOk()->assertSee('This link has been used');
    $this->post($url, ['password' => 'someone elses password', 'password_confirmation' => 'someone elses password'])->assertSee('This link has been used');

    expect(Hash::check('a long new password', $this->teacher->fresh()->password))->toBeTrue();
});

it('refuses tampered and expired links', function () {
    $url = AccountInvitation::url($this->teacher, $this->school);

    $this->get(str_replace($this->teacher->ulid, $this->admin->ulid, $url))->assertForbidden();

    $this->travel(AccountInvitation::DAYS_VALID + 1)->days();
    $this->get($url)->assertForbidden()->assertSee('This link has expired');
});

it('needs an email address and a member of this school', function () {
    $noEmail = memberOf($this->school, SchoolRole::Teacher, ['email' => null, 'phone' => '+2348039998888']);
    $outsider = User::factory()->create();

    $this->actingAs($this->admin)->post(route('school.members.invite', [$this->school, $noEmail->ulid]))->assertSessionHasErrors('email');
    $this->actingAs($this->admin)->post(route('school.members.invite', [$this->school, $outsider->ulid]))->assertNotFound();
    $this->actingAs($this->teacher)->post(route('school.members.invite', [$this->school, $this->admin->ulid]))->assertForbidden();
});
