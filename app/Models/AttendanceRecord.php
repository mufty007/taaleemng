<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One student on one day of the class register.
 *
 * @property AttendanceStatus $status
 * @property Carbon $date
 */
#[Fillable(['term_id', 'class_arm_id', 'enrolment_id', 'date', 'status', 'note', 'recorded_by'])]
class AttendanceRecord extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Enrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }
}
