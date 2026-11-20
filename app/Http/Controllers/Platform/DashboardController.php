<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SchoolStatus;
use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'stats' => [
                ['label' => 'Schools', 'value' => School::query()->count(), 'icon' => 'school', 'tone' => 'brand'],
                ['label' => 'On trial', 'value' => School::query()->where('status', SchoolStatus::Trial)->count(), 'icon' => 'sparkles', 'tone' => 'gold'],
                ['label' => 'Users', 'value' => User::query()->count(), 'icon' => 'users', 'tone' => 'sky'],
                ['label' => 'New leads', 'value' => DemoRequest::query()->unhandled()->count(), 'icon' => 'inbox', 'tone' => 'naija'],
            ],
            'recentLeads' => DemoRequest::query()->latest()->limit(5)->get(),
            'recentSchools' => School::query()->latest()->limit(5)->get(),
        ]);
    }
}
