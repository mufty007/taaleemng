<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules that only look inside the school in context.
 * A plain Rule::exists('class_arms') would accept another school's id.
 */
final class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return (new Exists($table, $column))->where('school_id', app(CurrentSchool::class)->getOrFail($table)->id);
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return (new Unique($table, $column))->where('school_id', app(CurrentSchool::class)->getOrFail($table)->id);
    }
}
