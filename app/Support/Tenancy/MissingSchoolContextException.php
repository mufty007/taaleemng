<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * Thrown when tenant data is queried or written without a school in context.
 *
 * Failing loudly here is deliberate: silently returning every school's rows
 * is how cross-tenant data leaks happen.
 */
class MissingSchoolContextException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("No school is in context while accessing [{$model}]. Run the code inside CurrentSchool::run() or a school route.");
    }
}
