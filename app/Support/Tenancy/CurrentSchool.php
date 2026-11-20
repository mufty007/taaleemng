<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Spatie\Permission\PermissionRegistrar;

/**
 * Holds the school the current request or job is acting for.
 *
 * Registered as a scoped singleton, so it resets between requests and queued jobs.
 * Nothing should ever read a school_id from user input; it comes from here.
 */
class CurrentSchool
{
    private ?School $school = null;

    public function __construct(private PermissionRegistrar $permissions) {}

    public function set(?School $school): void
    {
        $this->school = $school;
        $this->permissions->setPermissionsTeamId($school?->id);
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function getOrFail(string $context = 'tenant data'): School
    {
        return $this->school ?? throw MissingSchoolContextException::forModel($context);
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function check(): bool
    {
        return $this->school !== null;
    }

    public function forget(): void
    {
        $this->set(null);
    }

    /**
     * Run a callback with the given school in context, then restore the previous one.
     *
     * @template TReturn
     *
     * @param  callable(School): TReturn  $callback
     * @return TReturn
     */
    public function run(School $school, callable $callback): mixed
    {
        $previous = $this->school;
        $this->set($school);

        try {
            return $callback($school);
        } finally {
            $this->set($previous);
        }
    }
}
