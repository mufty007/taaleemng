<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\SchoolRole;
use App\Support\Tenancy\CurrentSchool;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, Notifiable, TwoFactorAuthenticatable;

    /**
     * @var array<string, mixed>
     */
    private array $schoolRecordCache = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Only the public `ulid` column is a ULID; the primary key stays an integer.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsToMany<School, $this, Membership, 'membership'>
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'status', 'joined_at', 'last_active_at', 'onboarding_completed_at'])
            ->withTimestamps();
    }

    /**
     * Schools this user can currently sign in to.
     *
     * @return BelongsToMany<School, $this, Membership, 'membership'>
     */
    public function activeSchools(): BelongsToMany
    {
        return $this->schools()->wherePivot('status', MembershipStatus::Active->value);
    }

    public function canAccessSchool(School $school): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        return $this->activeSchools()->whereKey($school->getKey())->exists();
    }

    /**
     * This person's staff record in the school in context, if they work there.
     */
    public function staffRecord(): ?Staff
    {
        return $this->rememberForSchool('staff', fn () => Staff::query()->where('user_id', $this->id)->first());
    }

    /**
     * This person's guardian record in the school in context, if they are a parent there.
     */
    public function guardianRecord(): ?Guardian
    {
        return $this->rememberForSchool('guardian', fn () => Guardian::query()->where('user_id', $this->id)->first());
    }

    /**
     * This person's student record in the school in context, if they study there.
     */
    public function studentRecord(): ?Student
    {
        return $this->rememberForSchool('student', fn () => Student::query()->where('user_id', $this->id)->first());
    }

    /**
     * Memoise a lookup per school, so switching school context never returns another school's record.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $resolve
     * @return TValue
     */
    private function rememberForSchool(string $key, callable $resolve): mixed
    {
        $cacheKey = app(CurrentSchool::class)->getOrFail(self::class)->id.'|'.$key;

        if (! array_key_exists($cacheKey, $this->schoolRecordCache)) {
            $this->schoolRecordCache[$cacheKey] = $resolve();
        }

        return $this->schoolRecordCache[$cacheKey];
    }

    /**
     * Whether the user holds any staff role in the school in context (as opposed to only parent or student).
     */
    public function isStaffInCurrentSchool(): bool
    {
        return $this->getRoleNames()->contains(fn (string $role): bool => SchoolRole::tryFrom($role)?->isStaff() ?? false);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Normalise a Nigerian phone number to E.164 (+234…), or null if it is not one.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            $digits === '' => null,
            str_starts_with($digits, '234') && strlen($digits) === 13 => '+'.$digits,
            str_starts_with($digits, '0') && strlen($digits) === 11 => '+234'.substr($digits, 1),
            strlen($digits) === 10 => '+234'.$digits,
            default => null,
        };
    }

    /**
     * A stored +234 number the way people write it locally: "0803 123 4567".
     */
    public static function displayPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $normalised = self::normalisePhone($phone);

        if ($normalised === null) {
            return $phone;
        }

        $local = '0'.substr($normalised, 4);

        return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
    }
}
