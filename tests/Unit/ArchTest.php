<?php

use App\Models\DemoRequest;
use App\Models\Membership;
use App\Models\PlatformBroadcast;
use App\Models\PlatformSetting;
use App\Models\School;
use App\Models\SchoolInvoice;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;

arch('tenant models are scoped to a school')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(BelongsToSchool::class)
    ->ignoring([
        // Platform-level models: they are not owned by a single school.
        User::class,
        School::class,
        Membership::class,
        DemoRequest::class,
        PlatformSetting::class,
        PlatformBroadcast::class,
        SchoolInvoice::class,
    ]);

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
