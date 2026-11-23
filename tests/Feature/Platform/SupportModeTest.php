<?php

use App\Enums\SchoolRole;
use App\Models\User;
use App\Support\Platform\ViewAs;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'name' => 'Greenfield Academy']);
    $this->admin = User::factory()->platformAdmin()->create(['name' => 'Musa Support']);
    $this->principal = memberOf($this->school, SchoolRole::Principal, ['name' => 'Funke Adebayo']);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'], ['email' => 'amina@family.test']);
    staffOf($this->school, attributes: ['first_name' => 'Emeka']);
});

it('opens every staff page of a school for a super admin, in support mode', function () {
    $pages = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
            && str_starts_with((string) $route->getName(), 'school.')
            && $route->parameterNames() === ['school']
            && ! str_starts_with((string) $route->getName(), 'school.portal'))
        ->map(fn (Route $route) => $route->getName())
        ->values();

    $broken = $pages->filter(fn (string $name) => $this->actingAs($this->admin)->get(route($name, $this->school))->getStatusCode() >= 500);

    expect($pages->count())->toBeGreaterThan(30)
        ->and($broken->all())->toBe([]);

    $this->actingAs($this->admin)->get(route('school.dashboard', $this->school))
        ->assertOk()
        ->assertSee('Support mode')
        ->assertSee('Back to console')
        ->assertSee('Payroll')
        ->assertSee('Users &amp; roles', false);
});

it('gives super admins a school admin’s reach, but not the portal or staff chat', function () {
    inSchool($this->school, function () {
        expect($this->admin->can('payroll.manage'))->toBeTrue()
            ->and($this->admin->can('school.manage'))->toBeTrue()
            ->and($this->admin->can('portal.wards'))->toBeFalse()
            ->and($this->admin->can('cbt.take'))->toBeFalse()
            ->and($this->admin->can('chat.use'))->toBeFalse();
    });
});

it('marks what a super admin does in a school as Ta’aleem support in the school’s log', function () {
    $this->actingAs($this->admin)->get(route('school.dashboard', $this->school))->assertOk();

    $entry = Activity::query()->where('school_id', $this->school->id)->where('description', 'signed in to the school')->sole();
    expect($entry->properties->get('support'))->toBeTrue();
});

it('lets a super admin view the school as a member of staff, then stop', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.view-as.start', [$this->school, $this->principal]))
        ->assertRedirect(route('school.dashboard', $this->school));

    $this->assertAuthenticatedAs($this->principal);
    expect(session(ViewAs::ADMIN))->toBe($this->admin->id);

    $this->get(route('school.dashboard', $this->school))
        ->assertOk()
        ->assertSee('Viewing as Funke Adebayo (Principal)')
        ->assertDontSee('Support mode');

    expect($this->principal->fresh()->last_login_at)->toBeNull()
        ->and(Activity::query()->where('log_name', 'platform')->where('description', 'viewed Greenfield Academy as Funke Adebayo')->exists())->toBeTrue()
        ->and(Activity::query()->where('school_id', $this->school->id)->where('log_name', 'support')->exists())->toBeTrue();

    $this->post(route('view-as.stop'))->assertRedirect(route('platform.schools.show', $this->school));

    $this->assertAuthenticatedAs($this->admin);
    expect(session()->has(ViewAs::ADMIN))->toBeFalse();
});

it('sends a view-as of a parent to the parent portal', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.view-as.start', [$this->school, $this->aisha->guardian->user]))
        ->assertRedirect(route('school.portal', $this->school));

    $this->get(route('school.portal', $this->school))->assertOk()->assertSee('Viewing as')->assertSee('Aisha');
});

it('only views as members of that school, never another super admin, and only for super admins', function () {
    $otherAdmin = memberOf($this->school, SchoolRole::SchoolAdmin, ['is_platform_admin' => true]);
    $outsider = memberOf(schoolWithRoles(), SchoolRole::Principal);
    $schoolAdmin = memberOf($this->school, SchoolRole::SchoolAdmin);

    $this->actingAs($this->admin)->post(route('platform.view-as.start', [$this->school, $otherAdmin]))->assertForbidden();
    $this->actingAs($this->admin)->post(route('platform.view-as.start', [$this->school, $outsider]))->assertNotFound();
    $this->actingAs($schoolAdmin)->post(route('platform.view-as.start', [$this->school, $this->principal]))->assertNotFound();
    $this->actingAs($this->admin)->post(route('view-as.stop'))->assertNotFound();
});

it('keeps the person’s account settings closed while viewing as them, and log out goes back', function () {
    $this->actingAs($this->admin)->post(route('platform.view-as.start', [$this->school, $this->principal]));

    $this->get(route('account'))->assertRedirect(route('app'))->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Not available while viewing as someone'));
    $this->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'hijacked-123', 'password_confirmation' => 'hijacked-123'])->assertRedirect(route('app'));
    $this->get(route('platform.dashboard'))->assertNotFound();

    $this->post(route('logout'))->assertRedirect(route('platform.schools.show', $this->school));
    $this->assertAuthenticatedAs($this->admin);
});
