<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block suspended or archived schools (platform admins can still look in).
 */
class EnsureSchoolIsActive
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $this->currentSchool->getOrFail();

        if (! $school->allowsAccess() && ! $request->user()?->is_platform_admin) {
            return response()->view('errors.school-unavailable', ['school' => $school], 403);
        }

        return $next($request);
    }
}
