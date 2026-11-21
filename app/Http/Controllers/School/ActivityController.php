<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Who did what, and when, in this school.
 */
class ActivityController extends Controller
{
    public function index(Request $request, CurrentSchool $currentSchool): View
    {
        $log = $request->query('log');

        return view('school.activity.index', [
            'activities' => Activity::query()
                ->where('school_id', $currentSchool->getOrFail()->id)
                ->when($log, fn ($query) => $query->where('log_name', $log))
                ->with('causer')
                ->latest()
                ->paginate(40)
                ->withQueryString(),
            'logs' => ['auth' => 'Sign-ins', 'academics' => 'Academics', 'people' => 'People', 'school' => 'School'],
            'selectedLog' => $log,
        ]);
    }
}
