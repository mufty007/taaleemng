<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dismiss the first-login role tour for this school membership.
 */
class OnboardingController extends Controller
{
    public function complete(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $school = $currentSchool->getOrFail();

        $request->user()->schools()->updateExistingPivot($school->id, [
            'onboarding_completed_at' => now(),
        ]);

        return back();
    }
}
