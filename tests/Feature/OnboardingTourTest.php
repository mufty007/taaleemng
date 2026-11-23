<?php

use App\Enums\SchoolRole;
use App\Models\User;

it('shows the role tour until Got it is clicked, then keeps it dismissed', function () {
    $school = schoolWithStructure();
    $principal = memberOf($school, SchoolRole::Principal);

    $this->actingAs($principal)
        ->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Welcome, principal')
        ->assertSee('Got it');

    $this->actingAs($principal)
        ->post(route('school.onboarding.complete', $school))
        ->assertRedirect();

    $membership = $principal->fresh()->schools()->where('schools.id', $school->id)->first()?->membership;

    expect($membership?->onboarding_completed_at)->not->toBeNull();

    $this->actingAs($principal)
        ->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertDontSee('Welcome, principal')
        ->assertDontSee('Got it');
});

it('keeps the tour to people in that school only', function () {
    $school = schoolWithStructure();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('school.onboarding.complete', $school))
        ->assertNotFound();
});
