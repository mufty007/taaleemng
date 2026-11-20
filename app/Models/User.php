<?php

namespace App\Models;

use App\Enums\MembershipStatus;
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
            ->withPivot(['id', 'status', 'joined_at', 'last_active_at'])
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
}
