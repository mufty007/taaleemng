<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Closure;

/**
 * Job middleware that puts the job's school back in context before it runs.
 */
class RestoreSchoolContext
{
    public function handle(object $job, Closure $next): mixed
    {
        $school = School::query()->findOrFail($job->schoolId ?? throw MissingSchoolContextException::forModel($job::class));

        return app(CurrentSchool::class)->run($school, fn () => $next($job));
    }
}
