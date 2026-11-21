<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An academic year such as 2026/2027, made of three terms.
 *
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'is_current'])]
class AcademicSession extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<Enrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public static function current(): ?self
    {
        return static::query()->where('is_current', true)->first();
    }

    /**
     * The session new applicants are admitted into: the next one once the school
     * has created it (admissions run ahead of September), otherwise the current one.
     */
    public static function forAdmissions(): ?self
    {
        $current = static::current();

        if ($current === null) {
            return null;
        }

        return static::query()->where('starts_on', '>', $current->starts_on)->orderBy('starts_on')->first() ?? $current;
    }
}
