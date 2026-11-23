<?php

namespace App\Http\Middleware;

use App\Support\Platform\Modules;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages of a module the school doesn't have (by plan, or switched off by a super admin)
 * explain that instead of opening.
 */
class EnsureModuleEnabled
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $this->currentSchool->get();
        $module = $school ? Modules::blocking($school, $request) : null;

        if ($module === null) {
            return $next($request);
        }

        $label = Modules::ALL[$module]['label'];

        if ($request->expectsJson()) {
            return response()->json(['message' => "{$label} isn’t switched on for {$school->name}."], 403);
        }

        return response()->view('school.module-off', ['label' => $label, 'school' => $school], 403);
    }
}
