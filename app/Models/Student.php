<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Gender $gender
 * @property StudentStatus $status
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $admitted_on
 */
#[Fillable([
    'user_id', 'admission_no', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
    'state_of_origin', 'lga', 'religion', 'blood_group', 'genotype', 'address', 'previous_school',
    'medical_notes', 'admitted_on', 'status',
])]
class Student extends Model
{
    use BelongsToSchool, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'admitted_on' => 'date',
            'medical_notes' => 'encrypted',
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
        return 'ulid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)->withPivot('is_primary')->withTimestamps();
    }

    /**
     * @return HasMany<Enrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The enrolment in the current academic session.
     *
     * @return HasOne<Enrolment, $this>
     */
    public function currentEnrolment(): HasOne
    {
        return $this->hasOne(Enrolment::class)->whereHas('academicSession', fn (Builder $query) => $query->where('is_current', true));
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    /**
     * Surname first, the way Nigerian school registers list students.
     */
    public function registerName(): string
    {
        return trim(Str::upper($this->last_name).' '.$this->first_name.' '.($this->middle_name ?? ''));
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1));
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * Students in the current session who are in one of the given class arms.
     *
     * @param  Builder<self>  $query
     * @param  list<int>  $classArmIds
     */
    public function scopeInClassArms(Builder $query, array $classArmIds): void
    {
        $query->whereHas('currentEnrolment', fn (Builder $enrolment) => $enrolment->whereIn('class_arm_id', $classArmIds));
    }
}
