<?php

namespace App\Actions\Timetable;

use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\TimetableEntry;
use App\Support\Timetable\Timetable;
use Illuminate\Support\Facades\DB;

/**
 * Fill a class's empty lesson periods for the week. Each subject gets its usual
 * number of lessons (English and Maths five, most others two or three), spread
 * across the days, and a teacher is never put in two classes at the same time.
 * Lessons already placed by hand are kept.
 */
class AutofillTimetable
{
    /**
     * @return array{placed: int, empty: int}
     */
    public function handle(ClassArm $arm): array
    {
        $periods = Period::query()->where('kind', 'lesson')->orderBy('position')->get();
        $classSubjects = ClassSubject::query()->with('subject')->where('class_arm_id', $arm->id)->get()
            ->sortBy([
                fn (ClassSubject $a, ClassSubject $b) => Timetable::weeklyTarget($b) <=> Timetable::weeklyTarget($a),
                fn (ClassSubject $a, ClassSubject $b) => $a->subject->name <=> $b->subject->name,
            ])
            ->values();

        if ($periods->isEmpty() || $classSubjects->isEmpty()) {
            return ['placed' => 0, 'empty' => 0];
        }

        $existing = TimetableEntry::query()->where('class_arm_id', $arm->id)->get();
        $taken = $existing->mapWithKeys(fn (TimetableEntry $entry) => ["{$entry->day}-{$entry->period_id}" => $entry->class_subject_id])->all();

        // Teachers' lessons in other classes: "teacherId-day-periodId".
        $teacherOf = $classSubjects->pluck('teacher_id', 'id');
        $busy = TimetableEntry::query()
            ->with('classSubject:id,teacher_id')
            ->where('class_arm_id', '!=', $arm->id)
            ->whereHas('classSubject', fn ($query) => $query->whereNotNull('teacher_id'))
            ->get()
            ->mapWithKeys(fn (TimetableEntry $entry) => ["{$entry->classSubject->teacher_id}-{$entry->day}-{$entry->period_id}" => true])
            ->all();

        $need = [];
        $perDay = [];

        foreach ($classSubjects as $classSubject) {
            $need[$classSubject->id] = Timetable::weeklyTarget($classSubject);
        }

        foreach ($taken as $slot => $classSubjectId) {
            $need[$classSubjectId] = ($need[$classSubjectId] ?? 0) - 1;
            $day = (int) explode('-', $slot)[0];
            $perDay[$classSubjectId][$day] = ($perDay[$classSubjectId][$day] ?? 0) + 1;
        }

        $rows = [];
        $now = now();

        // Go period by period across the week so each subject's lessons land on different days.
        foreach ($periods as $period) {
            foreach (array_keys(Period::DAYS) as $day) {
                if (isset($taken["{$day}-{$period->id}"])) {
                    continue;
                }

                $choice = $this->choose($classSubjects->all(), $need, $perDay, $teacherOf->all(), $busy, $day, $period->id);

                if ($choice === null) {
                    continue;
                }

                $taken["{$day}-{$period->id}"] = $choice;
                $need[$choice]--;
                $perDay[$choice][$day] = ($perDay[$choice][$day] ?? 0) + 1;

                if ($teacherOf[$choice]) {
                    $busy["{$teacherOf[$choice]}-{$day}-{$period->id}"] = true;
                }

                $rows[] = [
                    'school_id' => $arm->school_id, 'class_arm_id' => $arm->id, 'period_id' => $period->id, 'day' => $day,
                    'class_subject_id' => $choice, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        DB::table('timetable_entries')->insert($rows);

        return ['placed' => count($rows), 'empty' => $periods->count() * count(Period::DAYS) - count($taken)];
    }

    /**
     * The subject most in need of a lesson in this slot: its teacher is free, and it
     * has no lesson that day yet (or, once every subject has its share, at most two).
     *
     * @param  list<ClassSubject>  $classSubjects
     * @param  array<int, int>  $need
     * @param  array<int, array<int, int>>  $perDay
     * @param  array<int, int|null>  $teacherOf
     * @param  array<string, bool>  $busy
     */
    private function choose(array $classSubjects, array $need, array $perDay, array $teacherOf, array $busy, int $day, int $periodId): ?int
    {
        $free = array_filter($classSubjects, fn (ClassSubject $classSubject) => $teacherOf[$classSubject->id] === null
            || ! isset($busy["{$teacherOf[$classSubject->id]}-{$day}-{$periodId}"]));

        foreach ([fn (int $id) => $need[$id] > 0 && ($perDay[$id][$day] ?? 0) === 0, fn (int $id) => $need[$id] > 0 && ($perDay[$id][$day] ?? 0) < 2] as $rule) {
            $candidates = array_filter($free, fn (ClassSubject $classSubject) => $rule($classSubject->id));

            if ($candidates !== []) {
                usort($candidates, fn (ClassSubject $a, ClassSubject $b) => $need[$b->id] <=> $need[$a->id]);

                return $candidates[0]->id;
            }
        }

        return null;
    }
}
