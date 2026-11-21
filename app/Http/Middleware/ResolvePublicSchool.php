<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * For a school's public pages (the admission form): resolve the {school} slug
 * and put the school in context, with no sign-in. Suspended and archived
 * schools are hidden.
 */
class ResolvePublicSchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $request->route('school');

        if (! $school instanceof School) {
            $school = School::query()->where('slug', $school)->first();
        }

        abort_if($school === null || ! $school->allowsAccess(), 404);

        $this->currentSchool->set($school);
        URL::defaults(['school' => $school->slug]);
        $request->route()->forgetParameter('school');

        return $next($request);
    }
}
