<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\StaffStatus;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The office staff register for one day: who is employed, how each was marked,
 * and whether the day can still be changed.
 */
final class StaffRegister
{
    /**
     * @param  Collection<int, array{staff: int, name: string, staff_no: string, designation: ?string, status: ?string, note: string}>  $rows
     */
    private function __construct(
        public readonly Carbon $date,
        public readonly ?Term $term,
        public readonly Collection $rows,
        public readonly bool $locked,
    ) {}

    public static function for(Carbon $date): self
    {
        $term = ClassRegister::termFor($date);
        $staff = Staff::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $records = StaffAttendanceRecord::query()
            ->whereDate('date', $date)
            ->get()
            ->keyBy('staff_id');

        return new self(
            $date,
            $term,
            $staff->map(fn (Staff $member): array => [
                'staff' => $member->id,
                'name' => $member->fullName(),
                'staff_no' => $member->staff_no,
                'designation' => $member->designation,
                'status' => $records->get($member->id)?->status->value,
                'note' => $records->get($member->id)->note ?? '',
            ])->values(),
            $term === null || $term->isClosed() || $date->isFuture() || $date->isWeekend(),
        );
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status) => [$status->value => $this->rows->where('status', $status->value)->count()])
            ->all();
    }

    public function isTaken(): bool
    {
        return $this->rows->isNotEmpty() && $this->rows->whereNull('status')->isEmpty();
    }

    /**
     * Active staff ids eligible for the register.
     *
     * @return list<int>
     */
    public static function staffIds(): array
    {
        return Staff::query()->where('status', StaffStatus::Active)->pluck('id')->all();
    }
}
