<?php

namespace App\Support\Tenancy;

use App\Models\School;

/**
 * Use on queued jobs that touch tenant data.
 *
 * Call captureSchool() in the job's constructor (while the request still has a
 * school in context); the job then runs inside that school when the worker picks it up.
 */
trait SchoolAware
{
    public ?int $schoolId = null;

    protected function captureSchool(?School $school = null): void
    {
        $this->schoolId = $school->id ?? app(CurrentSchool::class)->getOrFail(static::class)->id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RestoreSchoolContext];
    }
}
