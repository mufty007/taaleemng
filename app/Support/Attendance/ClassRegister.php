<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One class's register for one day: who is in the class, how each was marked,
 * and whether the day can still be changed. Shared by the register page and
 * the register tab on the class page.
 */
final class ClassRegister
{
    /**
     * @param  Collection<int, array{enrolment: int, name: string, admission_no: string, status: ?string, note: string, rate: ?int}>  $rows
     */
    private function __construct(
        public readonly ClassArm $arm,
        public readonly Carbon $date,
        public readonly ?Term $term,
        public readonly Collection $rows,
        public readonly bool $locked,
    ) {}

    public static function for(ClassArm $arm, Carbon $date): self
    {
        $term = self::termFor($date);
        $enrolments = self::enrolments($arm, $term);
        $records = AttendanceRecord::query()->where('class_arm_id', $arm->id)->whereDate('date', $date)->get()->keyBy('enrolment_id');
        $summary = $term ? AttendanceSummary::forTerm($term, $enrolments->pluck('id')->all()) : [];

        return new self(
            $arm,
            $date,
            $term,
            $enrolments->map(fn (Enrolment $enrolment): array => [
                'enrolment' => $enrolment->id,
                'name' => $enrolment->student->registerName(),
                'admission_no' => $enrolment->student->admission_no,
                'status' => $records->get($enrolment->id)?->status->value,
                'note' => $records->get($enrolment->id)->note ?? '',
                'rate' => $summary[$enrolment->id]['rate'] ?? null,
            ])->values(),
            $term === null || $term->isClosed() || $date->isFuture() || $date->isWeekend(),
        );
    }

    /**
     * How many were marked with each status, e.g. ['present' => 30, 'absent' => 2].
     *
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

    public static function termFor(Carbon $date): ?Term
    {
        return Term::query()->with('academicSession')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    /**
     * @return Collection<int, Enrolment>
     */
    public static function enrolments(ClassArm $arm, ?Term $term): Collection
    {
        $sessionId = $term->academic_session_id ?? AcademicSession::current()->id ?? 0;

        return Enrolment::query()
            ->with('student')
            ->where('class_arm_id', $arm->id)
            ->where('academic_session_id', $sessionId)
            ->get()
            ->sortBy(fn (Enrolment $enrolment) => $enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->values();
    }

    /**
     * The school day a date means: weekends open on the Friday before.
     */
    public static function schoolDay(?string $date): Carbon
    {
        try {
            $day = filled($date) ? Carbon::parse($date) : today();
        } catch (\Throwable) {
            $day = today();
        }

        return $day->isWeekend() ? $day->previous(Carbon::FRIDAY) : $day->startOfDay();
    }
}
