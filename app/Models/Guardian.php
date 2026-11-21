<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A parent or guardian in one school. Siblings share the same guardian record (matched by phone).
 *
 * @property GuardianRelationship $relationship
 */
#[Fillable(['user_id', 'title', 'first_name', 'last_name', 'relationship', 'phone', 'email', 'occupation', 'address'])]
class Guardian extends Model
{
    use BelongsToSchool, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('is_primary')->withTimestamps();
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->title, $this->first_name, $this->last_name])));
    }
}
