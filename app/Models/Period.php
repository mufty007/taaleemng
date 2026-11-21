<?php

namespace App\Models;

use App\Enums\PeriodKind;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One slot in the school day's bell schedule: a lesson period, a break or assembly.
 *
 * @property PeriodKind $kind
 * @property string $starts_at
 * @property string $ends_at
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'kind', 'position'])]
class Period extends Model
{
    use BelongsToSchool;

    /** A typical Nigerian secondary school day. */
    public const DEFAULTS = [
        ['Assembly', '07:30', '07:50', 'assembly'],
        ['Period 1', '07:50', '08:30', 'lesson'],
        ['Period 2', '08:30', '09:10', 'lesson'],
        ['Period 3', '09:10', '09:50', 'lesson'],
        ['Period 4', '09:50', '10:30', 'lesson'],
        ['Short break', '10:30', '10:50', 'break'],
        ['Period 5', '10:50', '11:30', 'lesson'],
        ['Period 6', '11:30', '12:10', 'lesson'],
        ['Period 7', '12:10', '12:50', 'lesson'],
        ['Long break', '12:50', '13:20', 'break'],
        ['Period 8', '13:20', '14:00', 'lesson'],
    ];

    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PeriodKind::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<TimetableEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * "7:50 – 8:30"
     */
    public function times(): string
    {
        return Carbon::parse($this->starts_at)->format('g:i').' – '.Carbon::parse($this->ends_at)->format('g:i');
    }

    public function isNow(): bool
    {
        $now = now()->format('H:i:s');

        return $now >= Carbon::parse($this->starts_at)->format('H:i:s') && $now < Carbon::parse($this->ends_at)->format('H:i:s');
    }
}
