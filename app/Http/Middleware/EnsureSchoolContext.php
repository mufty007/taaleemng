<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the {school} route parameter, check the user belongs to it, and put it in context.
 *
 * After this runs, route('school.*') URLs fill in the school automatically and
 * controllers don't receive the school parameter; they use CurrentSchool instead.
 */
class EnsureSchoolContext
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $request->route('school');

        if (! $school instanceof School) {
            $school = School::query()->where('slug', $school)->first();
        }

        $user = $request->user();

        // 404 rather than 403, so school slugs can't be probed.
        abort_if($school === null || $user === null || ! $user->canAccessSchool($school), 404);

        $this->currentSchool->set($school);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        URL::defaults(['school' => $school->slug]);
        $request->route()->forgetParameter('school');

        $this->touchMembership($request, $school);

        return $next($request);
    }

    private function touchMembership(Request $request, School $school): void
    {
        $key = "school.{$school->id}.last_active_touched";

        if ($request->session()->get($key, 0) > now()->subMinutes(5)->getTimestamp()) {
            return;
        }

        $request->user()->schools()->updateExistingPivot($school->id, ['last_active_at' => now()]);
        $request->session()->put($key, now()->getTimestamp());
    }
}
