<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where everyone lands after signing in: straight into their school, a school
 * picker if they belong to several, or the platform console for platform admins.
 */
class AppEntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        $schools = $user->activeSchools()->orderBy('name')->get();

        $lastSlug = $request->session()->get('last_school');
        $lastSchool = $schools->firstWhere('slug', $lastSlug);

        return match (true) {
            $lastSchool !== null => redirect()->route('school.dashboard', $lastSchool),
            $schools->count() === 1 => redirect()->route('school.dashboard', $schools->first()),
            $schools->count() > 1 => redirect()->route('schools.pick'),
            $user->is_platform_admin => redirect()->route('platform.dashboard'),
            default => view('app.no-school'),
        };
    }
}
