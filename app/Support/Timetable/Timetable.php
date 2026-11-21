<?php

namespace App\Support\Timetable;

use App\Enums\SubjectCategory;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\Staff;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;

/**
 * Reading the timetable: the bell schedule, a class's or a teacher's week as a
 * grid, today's lessons, and whether a teacher is already teaching elsewhere.
 */
final class Timetable
{
    /**
     * @return Collection<int, Period>
     */
    public static function periods(): Collection
    {
        return Period::query()->orderBy('position')->orderBy('starts_at')->get();
    }

    /**
     * @return Collection<int, TimetableEntry>
     */
    public static function forClass(ClassArm $arm): Collection
    {
        return TimetableEntry::query()
            ->with(['classSubject.subject', 'classSubject.teacher'])
            ->where('class_arm_id', $arm->id)
            ->get();
    }

    /**
     * @return Collection<int, TimetableEntry>
     */
    public static function forTeacher(Staff $staff): Collection
    {
        return TimetableEntry::query()
            ->with(['classSubject.subject', 'classArm.level'])
            ->whereHas('classSubject', fn ($query) => $query->where('teacher_id', $staff->id))
            ->get();
    }

    /**
     * Entries arranged as [period id][day] => entry.
     *
     * @param  Collection<int, TimetableEntry>  $entries
     * @return array<int, array<int, TimetableEntry>>
     */
    public static function grid(Collection $entries): array
    {
        $grid = [];

        foreach ($entries as $entry) {
            $grid[$entry->period_id][$entry->day] = $entry;
        }

        return $grid;
    }

    /**
     * Today's lessons in period order, with the period loaded.
     *
     * @param  Collection<int, TimetableEntry>  $entries
     * @return Collection<int, TimetableEntry>
     */
    public static function today(Collection $entries): Collection
    {
        $day = now()->dayOfWeekIso;

        return $entries->where('day', $day)
            ->each(fn (TimetableEntry $entry) => $entry->loadMissing('period'))
            ->sortBy(fn (TimetableEntry $entry) => sprintf('%05d', $entry->period->position))
            ->values();
    }

    /**
     * The lesson the class subject's teacher already has in another class at that time, if any.
     */
    public static function clash(ClassSubject $classSubject, int $day, int $periodId): ?TimetableEntry
    {
        if ($classSubject->teacher_id === null) {
            return null;
        }

        return TimetableEntry::query()
            ->with('classArm.level')
            ->where('day', $day)
            ->where('period_id', $periodId)
            ->where('class_arm_id', '!=', $classSubject->class_arm_id)
            ->whereHas('classSubject', fn ($query) => $query->where('teacher_id', $classSubject->teacher_id))
            ->first();
    }

    /**
     * How many lessons a week a subject usually gets.
     */
    public static function weeklyTarget(ClassSubject $classSubject): int
    {
        return match (true) {
            in_array($classSubject->subject->code, ['ENG', 'MTH'], true) => 5,
            in_array($classSubject->subject->code, ['QUR', 'ARB'], true) => 4,
            $classSubject->subject->category === SubjectCategory::Core => 3,
            $classSubject->subject->category === SubjectCategory::Elective => 3,
            default => 2,
        };
    }
}
