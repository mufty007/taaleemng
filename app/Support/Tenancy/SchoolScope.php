<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class SchoolScope implements Scope
{
    /**
     * Limit every query on a tenant model to the school in context.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $school = app(CurrentSchool::class)->getOrFail($model::class);

        $builder->where($model->qualifyColumn('school_id'), $school->id);
    }
}
