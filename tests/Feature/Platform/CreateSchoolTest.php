<?php

use App\Actions\Platform\CreateSchool;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Models\DemoRequest;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->create();
});

it('creates a school on trial with its roles and first admin', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('platform.schools.store'), [
            'name' => 'Greenfield Academy, Abuja',
            'school_type' => 'combined',
            'state' => 'FCT',
            'admin_name' => 'Ngozi Okafor',
            'admin_email' => 'Ngozi@Greenfield.test',
        ])
        ->assertRedirect(route('platform.schools.index'))
        ->assertSessionHas('status');

    $school = School::query()->sole();
    $proprietor = User::query()->where('email', 'ngozi@greenfield.test')->sole();

    expect($school)
        ->slug->toBe('greenfield-academy-abuja')
        ->short_code->toBe('GAA')
        ->status->toBe(SchoolStatus::Trial)
        ->and($school->trial_ends_at->isFuture())->toBeTrue()
        ->and($proprietor->canAccessSchool($school))->toBeTrue();

    app(CurrentSchool::class)->run($school, function () use ($proprietor) {
        expect(Role::query()->where('school_id', app(CurrentSchool::class)->id())->count())->toBe(count(SchoolRole::cases()))
            ->and($proprietor->hasRole(SchoolRole::SchoolAdmin))->toBeTrue()
            ->and($proprietor->can('users.manage'))->toBeTrue();
    });

    Notification::assertSentTo($proprietor, ResetPassword::class);
});

it('gives each school its own slug and code, avoiding reserved words', function () {
    Notification::fake();
    $create = app(CreateSchool::class);

    $first = $create->handle(['name' => 'Schools', 'school_type' => 'secondary'], ['name' => 'A', 'email' => 'a@test.ng']);
    $second = $create->handle(['name' => 'Schools', 'school_type' => 'secondary'], ['name' => 'B', 'email' => 'b@test.ng']);

    expect($first->slug)->toBe('schools-2')
        ->and($second->slug)->toBe('schools-3')
        ->and($first->short_code)->not->toBe($second->short_code);
});

it('adds an existing user to a new school without resetting their password', function () {
    Notification::fake();
    $existing = User::factory()->create(['email' => 'sani@teachers.test']);

    $this->actingAs($this->admin)->post(route('platform.schools.store'), [
        'name' => 'Al-Noor Model School',
        'school_type' => 'islamiyya',
        'admin_name' => 'Sani',
        'admin_email' => 'sani@teachers.test',
    ])->assertRedirect();

    expect($existing->canAccessSchool(School::query()->sole()))->toBeTrue();
    Notification::assertNothingSent();
});

it('validates the new school form', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.schools.store'), ['school_type' => 'university', 'state' => 'Atlantis'])
        ->assertSessionHasErrors(['name', 'school_type', 'state', 'admin_name', 'admin_email']);
});

it('hides the platform console from everyone else', function () {
    $school = schoolWithRoles();
    $proprietor = memberOf($school);

    $this->actingAs($proprietor)->get(route('platform.dashboard'))->assertNotFound();
    $this->actingAs($proprietor)->post(route('platform.schools.store'), [])->assertNotFound();
});

it('lists schools and leads for platform admins', function () {
    schoolWithRoles(['name' => 'Greenfield Academy']);
    DemoRequest::factory()->create(['school_name' => 'Crescent College']);

    $this->actingAs($this->admin)->get(route('platform.dashboard'))->assertOk()->assertSee('Crescent College');
    $this->actingAs($this->admin)->get(route('platform.schools.index', ['search' => 'Green']))->assertOk()->assertSee('Greenfield Academy');
    $this->actingAs($this->admin)->get(route('platform.schools.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('platform.leads.index'))->assertOk()->assertSee('Crescent College');
});

it('marks a lead as handled and back again', function () {
    $lead = DemoRequest::factory()->create();

    $this->actingAs($this->admin)->patch(route('platform.leads.update', $lead))->assertRedirect();
    expect($lead->fresh()->handled_at)->not->toBeNull();

    $this->actingAs($this->admin)->patch(route('platform.leads.update', $lead))->assertRedirect();
    expect($lead->fresh()->handled_at)->toBeNull();
});
