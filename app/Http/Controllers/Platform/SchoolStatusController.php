<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SchoolStatus;
use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolStatusController extends Controller
{
    public function __invoke(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(SchoolStatus::class)],
        ]);

        $status = SchoolStatus::from($data['status']);
        $school->update([
            'status' => $status,
            'trial_ends_at' => $status === SchoolStatus::Trial ? now()->addDays((int) config('taaleem.trial_days')) : $school->trial_ends_at,
        ]);

        activity('platform')
            ->performedOn($school)
            ->tap(fn ($activity) => $activity->school_id = $school->id)
            ->log("changed the school’s status to {$status->label()}");

        return back()->with('status', "{$school->name} is now {$status->label()}.");
    }
}
