<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Marks a model as tenant data owned by one school.
 *
 * - Queries are limited to the school in context (and throw without one).
 * - New rows are stamped with the current school.
 * - A row's school can never change once written.
 *
 * @mixin Model
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            $current = app(CurrentSchool::class)->getOrFail($model::class);

            if ($model->getAttribute('school_id') === null) {
                $model->setAttribute('school_id', $current->id);

                return;
            }

            if ((int) $model->getAttribute('school_id') !== $current->id) {
                throw new LogicException('Refusing to create ['.$model::class.'] for a school other than the one in context.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('school_id')) {
                throw new LogicException('The school of ['.$model::class.'] cannot be changed.');
            }
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
