<?php

use App\Enums\BillingModel;
use App\Enums\ClassStructure;
use App\Enums\Plan;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;

it('lets a proprietor start a trial school from the public signup page', function () {
    $this->get(route('signup'))->assertOk()->assertSee('Start free trial')->assertSee('Starter');

    $this->post(route('signup.store'), [
        'school_name' => 'Crescent College',
        'school_type' => 'secondary',
        'state' => 'Lagos',
        'plan' => 'standard',
        'billing_model' => 'flat',
        'billing_cycle' => 'termly',
        'class_structure' => 'arms',
        'admin_name' => 'Amina Bello',
        'admin_email' => 'amina@crescent.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect();

    $school = School::query()->where('slug', 'like', 'crescent-college%')->first();
    expect($school)->not->toBeNull()
        ->and($school->status)->toBe(SchoolStatus::Trial)
        ->and($school->plan)->toBe(Plan::Standard)
        ->and($school->billing_model)->toBe(BillingModel::Flat)
        ->and($school->class_structure)->toBe(ClassStructure::Arms);

    $this->assertAuthenticatedAs(User::query()->where('email', 'amina@crescent.test')->first());
});
