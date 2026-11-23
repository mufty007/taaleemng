<?php

namespace App\Models;

use App\Enums\BroadcastAudience;
use App\Enums\BroadcastLevel;
use App\Enums\SchoolRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A notice from Ta'aleem to schools (maintenance, new features, payment reminders),
 * shown as a banner to the chosen audience until it ends or they dismiss it.
 *
 * @property BroadcastLevel $level
 * @property BroadcastAudience $audience
 * @property list<int>|null $school_ids
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 */
#[Fillable(['title', 'body', 'level', 'audience', 'school_ids', 'starts_at', 'ends_at', 'email_admins', 'created_by'])]
class PlatformBroadcast extends Model
{
    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => BroadcastLevel::class,
            'audience' => BroadcastAudience::class,
            'school_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'email_admins' => 'boolean',
        ];
    }

    /**
     * People who closed the banner.
     *
     * @return BelongsToMany<User, $this>
     */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * "Live", "Scheduled" or "Ended".
     */
    public function state(): string
    {
        return match (true) {
            $this->ends_at !== null && $this->ends_at->lte(now()) => 'Ended',
            $this->starts_at->gt(now()) => 'Scheduled',
            default => 'Live',
        };
    }

    public function isLive(): bool
    {
        return $this->state() === 'Live';
    }

    /**
     * Live broadcasts this person should see in this school, not yet dismissed.
     *
     * @return Collection<int, self>
     */
    public static function forViewer(User $user, School $school): Collection
    {
        return self::query()
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn (Builder $query) => $query->whereNull('school_ids')->orWhereJsonContains('school_ids', $school->id))
            ->whereDoesntHave('dismissedBy', fn (Builder $query) => $query->whereKey($user->id))
            ->latest('starts_at')
            ->get()
            ->filter(fn (self $broadcast): bool => match ($broadcast->audience) {
                BroadcastAudience::Everyone => true,
                BroadcastAudience::Staff => $user->isStaffInCurrentSchool() || $user->is_platform_admin,
                BroadcastAudience::Admins => $user->hasRole(SchoolRole::SchoolAdmin->value) || $user->is_platform_admin,
            })
            ->values();
    }
}
