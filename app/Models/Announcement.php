<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A notice from the school: to everyone, to staff, parents or students, or to one class
 * (its students, their parents and the teachers who teach it).
 *
 * @property AnnouncementAudience $audience
 * @property Carbon $published_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['title', 'body', 'audience', 'class_arm_id', 'is_pinned', 'published_at', 'expires_at', 'created_by'])]
class Announcement extends Model
{
    use BelongsToSchool, HasUlids;

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
            'audience' => AnnouncementAudience::class,
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClassArm, $this>
     */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * "Everyone", "Parents", "JSS 2A"…
     */
    public function audienceLabel(): string
    {
        return $this->audience === AnnouncementAudience::ClassArm ? ($this->classArm?->displayName() ?? 'One class') : $this->audience->label();
    }

    /**
     * Notices this person should see now: live (published, not expired) and meant for them.
     * Whoever runs academics sees them all.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($user->can('academics.manage')) {
            return;
        }

        $staff = $user->staffRecord();
        $student = $user->studentRecord();
        $guardian = $user->guardianRecord();

        $classIds = collect($staff?->classArmIds() ?? [])
            ->merge($student?->currentEnrolment ? [$student->currentEnrolment->class_arm_id] : [])
            ->merge($guardian ? Enrolment::query()->whereIn('student_id', $guardian->students()->pluck('students.id'))
                ->whereHas('academicSession', fn (Builder $query) => $query->where('is_current', true))->pluck('class_arm_id') : [])
            ->unique()
            ->values()
            ->all();

        $audiences = array_values(array_filter([
            AnnouncementAudience::Everyone,
            $user->isStaffInCurrentSchool() ? AnnouncementAudience::Staff : null,
            $guardian ? AnnouncementAudience::Parents : null,
            $student ? AnnouncementAudience::Students : null,
        ]));

        $query->where(fn (Builder $query) => $query
            ->whereIn('audience', $audiences)
            ->orWhere(fn (Builder $query) => $query->where('audience', AnnouncementAudience::ClassArm)->whereIn('class_arm_id', $classIds)));
    }

    /**
     * Pinned first, then newest.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNoticeBoardOrder(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isReadBy(User $user): bool
    {
        return $this->relationLoaded('reads')
            ? $this->reads->contains('user_id', $user->id)
            : $this->reads()->where('user_id', $user->id)->exists();
    }
}
