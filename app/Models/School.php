<?php

namespace App\Models;

use App\Enums\SchoolStatus;
use App\Enums\SchoolType;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A tenant. Platform-level model: it is not itself scoped by school.
 *
 * @property SchoolStatus $status
 * @property SchoolType $school_type
 */
#[Fillable([
    'name', 'slug', 'short_code', 'logo_path', 'motto', 'address', 'state', 'lga',
    'phone', 'email', 'website', 'school_type', 'curriculum_type', 'status', 'trial_ends_at',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SchoolStatus::class,
            'school_type' => SchoolType::class,
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<User, $this, Membership, 'membership'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'status', 'joined_at', 'last_active_at'])
            ->withTimestamps();
    }

    public function allowsAccess(): bool
    {
        return $this->status->allowsAccess();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->replaceMatches('/[^A-Za-z\s]/', '')
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
