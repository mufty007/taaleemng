<?php

use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->create();
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'name' => 'Greenfield Academy', 'short_code' => 'GFA', 'state' => 'Lagos', 'status' => SchoolStatus::Trial, 'trial_ends_at' => now()->addDays(5)]);
    $this->principal = memberOf($this->school, SchoolRole::Principal, ['name' => 'Funke Adebayo', 'email' => 'funke@greenfield.test']);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'], ['first_name' => 'Amina', 'last_name' => 'Bello', 'email' => 'amina@family.test']);
    $this->other = schoolWithRoles(['name' => 'Royal Crest College', 'state' => 'Kano', 'status' => SchoolStatus::Suspended]);
});

it('shows the platform at a glance, with trials about to end', function () {
    $this->actingAs($this->admin)->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Trials ending soon')
        ->assertSee('Greenfield Academy')
        ->assertSee('5 days left')
        ->assertSee('Schools active each day')
        ->assertSee('Platform status');
});

it('lists schools with their numbers, and filters by status, state and search', function () {
    $list = fn (array $query = []) => $this->actingAs($this->admin)->get(route('platform.schools.index', $query))->assertOk();

    $list()->assertSee('Greenfield Academy')->assertSee('Royal Crest College')->assertSee('Suspended');
    $list(['status' => 'suspended'])->assertSee('Royal Crest College')->assertDontSee('Greenfield Academy');
    $list(['state' => 'Lagos'])->assertSee('Greenfield Academy')->assertDontSee('Royal Crest College');
    $list(['search' => 'GFA'])->assertSee('Greenfield Academy')->assertDontSee('Royal Crest College');

    $greenfield = $list()->viewData('schools')->firstWhere('slug', 'greenfield');
    expect($greenfield->students_count)->toBe(1);
});

it('shows a school’s overview, people, activity and settings in one page', function () {
    $this->actingAs($this->admin)->get(route('school.dashboard', $this->school));

    $this->actingAs($this->admin)->get(route('platform.schools.show', $this->school))
        ->assertOk()
        ->assertSee('Getting set up')
        ->assertSee('Funke Adebayo')
        ->assertSee('View as')
        ->assertDontSee('Amina Bello')
        ->assertSee('Support')
        ->assertSee('School details')
        ->assertSee('+14 days');

    $this->actingAs($this->admin)->get(route('platform.schools.show', [$this->school, 'who' => 'parents']))
        ->assertSee('Amina Bello')
        ->assertDontSee('Funke Adebayo</span>', false);
});

it('corrects a school’s details and keeps web addresses unique', function () {
    $form = ['name' => 'Greenfield Academy, Lekki', 'slug' => 'greenfield-lekki', 'short_code' => 'gfl', 'school_type' => 'combined', 'state' => 'Lagos', 'phone' => '0803 123 4567'];

    $this->actingAs($this->admin)->put(route('platform.schools.update', $this->school), [...$form, 'slug' => $this->other->slug])->assertSessionHasErrors('slug', errorBag: 'details');
    $this->actingAs($this->admin)->put(route('platform.schools.update', $this->school), [...$form, 'slug' => 'platform'])->assertSessionHasErrors('slug', errorBag: 'details');
    $this->actingAs($this->admin)->put(route('platform.schools.update', $this->school), $form)->assertSessionHasNoErrors();

    $school = $this->school->fresh();
    expect($school->name)->toBe('Greenfield Academy, Lekki')
        ->and($school->slug)->toBe('greenfield-lekki')
        ->and($school->short_code)->toBe('GFL')
        ->and(Activity::query()->where('log_name', 'platform')->where('description', 'like', 'updated the school’s details%')->exists())->toBeTrue();
});

it('extends a trial from its current end date, and only for schools on trial', function () {
    $ends = $this->school->trial_ends_at;

    $this->actingAs($this->admin)->post(route('platform.schools.trial', $this->school), ['days' => 14])->assertRedirect();
    expect($this->school->fresh()->trial_ends_at->toDateString())->toBe($ends->copy()->addDays(14)->toDateString());

    $this->actingAs($this->admin)->post(route('platform.schools.trial', $this->other), ['days' => 14])->assertStatus(422);
    $this->actingAs($this->admin)->post(route('platform.schools.trial', $this->school), ['days' => 365])->assertSessionHasErrors('days');
});

it('records why a school’s status changed', function () {
    $this->actingAs($this->admin)->patch(route('platform.schools.status', $this->school), ['status' => 'suspended', 'reason' => 'Unpaid since September'])->assertRedirect();

    expect($this->school->fresh()->status)->toBe(SchoolStatus::Suspended)
        ->and(Activity::query()->where('description', 'changed the school’s status to Suspended: Unpaid since September')->exists())->toBeTrue();
});

it('sends an invitation to someone who never signed in, and a reset link to anyone else', function () {
    Notification::fake();
    $send = fn (User $user) => $this->actingAs($this->admin)->post(route('platform.schools.members.sign-in-link', [$this->school, $user]));

    $send($this->principal)->assertSessionHas('status', 'Sent Funke Adebayo an invitation to choose a password at funke@greenfield.test.');
    Notification::assertSentTo($this->principal, AccountInvitation::class);

    $this->principal->forceFill(['last_login_at' => now()])->save();
    $send($this->principal->fresh())->assertSessionHas('status', fn (string $status) => str_contains($status, 'password-reset link'));
    Notification::assertSentTo($this->principal, ResetPassword::class);

    $outsider = memberOf($this->other, SchoolRole::Principal);
    $send($outsider)->assertNotFound();
});

it('keeps the console to super admins', function () {
    $schoolAdmin = memberOf($this->school, SchoolRole::SchoolAdmin);

    foreach ([route('platform.dashboard'), route('platform.schools.index'), route('platform.schools.show', $this->school)] as $url) {
        $this->actingAs($schoolAdmin)->get($url)->assertNotFound();
    }

    $this->actingAs($schoolAdmin)->put(route('platform.schools.update', $this->school), ['name' => 'Hacked'])->assertNotFound();
    expect(School::query()->where('name', 'Hacked')->exists())->toBeFalse();
});
