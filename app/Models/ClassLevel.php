<?php

namespace App\Models;

use App\Enums\ClassSection;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A year group such as Primary 4 or JSS 2. Its arms (JSS 2A, 2B) are the actual classes.
 *
 * @property ClassSection $section
 */
#[Fillable(['name', 'short_name', 'section', 'position', 'grading_scheme_id'])]
class ClassLevel extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => ClassSection::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GradingScheme, $this>
     */
    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    /**
     * @return HasMany<ClassArm, $this>
     */
    public function arms(): HasMany
    {
        return $this->hasMany(ClassArm::class)->orderBy('name');
    }

    /**
     * The class students move up to next session, or null for the school's last class.
     */
    public function nextLevel(): ?self
    {
        return static::query()->where('position', '>', $this->position)->orderBy('position')->first();
    }

    /**
     * Whether students leave the school after this class (e.g. SSS 3, or Primary 6 in a primary school).
     */
    public function isFinal(): bool
    {
        return ! static::query()->where('position', '>', $this->position)->exists();
    }
}
