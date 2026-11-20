<?php

namespace App\Http\Controllers\School;

use App\Enums\MembershipStatus;
use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentSchool $currentSchool): View
    {
        $school = $currentSchool->getOrFail();
        $user = $request->user();

        $request->session()->put('last_school', $school->slug);

        $memberCount = $school->users()->wherePivot('status', MembershipStatus::Active->value)->count();

        $roles = $user->getRoleNames()
            ->map(fn (string $role): string => SchoolRole::tryFrom($role)?->label() ?? $role)
            ->whenEmpty(fn ($roles) => $roles->push($user->is_platform_admin ? 'Platform admin' : 'Member'));

        return view('school.dashboard', [
            'school' => $school,
            'roles' => $roles,
            'stats' => [
                ['label' => 'Students', 'value' => '0', 'hint' => 'Enrol from Admissions', 'icon' => 'graduation-cap', 'tone' => 'brand'],
                ['label' => 'People with access', 'value' => (string) $memberCount, 'hint' => 'Staff and parents', 'icon' => 'users', 'tone' => 'naija'],
                ['label' => 'Classes', 'value' => '0', 'hint' => 'Nursery to SS 3', 'icon' => 'layers', 'tone' => 'sky'],
                ['label' => 'Fees collected', 'value' => '₦0', 'hint' => 'This term', 'icon' => 'wallet', 'tone' => 'gold'],
            ],
            'setupSteps' => [
                ['title' => 'Add your school details', 'body' => 'Logo, motto, address and signatures for report cards and receipts.', 'icon' => 'school', 'done' => filled($school->address) && filled($school->phone)],
                ['title' => 'Set up the session and terms', 'body' => 'Create 2026/2027 with First, Second and Third Term dates.', 'icon' => 'calendar-range', 'done' => false],
                ['title' => 'Create classes and subjects', 'body' => 'Nursery to SS 3, arms like JSS 1A, and the subjects each class takes.', 'icon' => 'layers', 'done' => false],
                ['title' => 'Bring in your students', 'body' => 'Import your student list from Excel, or enrol them one by one.', 'icon' => 'graduation-cap', 'done' => false],
                ['title' => 'Invite your staff', 'body' => 'Give the principal, bursar and teachers their own logins.', 'icon' => 'user-plus', 'done' => $memberCount > 1],
            ],
        ]);
    }
}
