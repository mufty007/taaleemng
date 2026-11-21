<?php

namespace App\Actions\Academic;

use App\Enums\ClassSection;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\ExamPaper;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Draft the end-of-term exam timetable: from a start date, each class level sits
 * one of its subjects in each daily slot, English and Maths first, weekdays only.
 * Levels write at the same time in their own halls, so a level never has two papers
 * at once. Papers already on the timetable are kept and not repeated.
 */
class GenerateExamTimetable
{
    /**
     * @param  list<string>  $slots  start times, e.g. ['08:30', '11:30']
     * @param  list<int>|null  $levelIds  only these levels, or every level with classes
     * @return array{created: int, last_day: ?Carbon}
     */
    public function handle(Term $term, Carbon $start, array $slots, ?array $levelIds = null): array
    {
        sort($slots);

        $levels = ClassLevel::query()
            ->whereHas('arms.classSubjects')
            ->when($levelIds !== null, fn ($query) => $query->whereIn('id', $levelIds))
            ->orderBy('position')
            ->get();

        $created = 0;
        $lastDay = null;
        $now = now();
        $rows = [];

        foreach ($levels as $level) {
            $scheduled = ExamPaper::query()->where('term_id', $term->id)->where('class_level_id', $level->id)->pluck('subject_id')->flip();
            $subjects = ClassSubject::query()->with('subject')
                ->whereIn('class_arm_id', $level->arms()->pluck('id'))
                ->get()
                ->pluck('subject')
                ->unique('id')
                ->reject(fn ($subject) => $scheduled->has($subject->id))
                ->sortBy(fn ($subject) => (in_array($subject->code, ['ENG', 'MTH'], true) ? '0' : '1').$subject->name)
                ->values();

            $day = $start->copy()->startOfDay();
            $slot = 0;

            foreach ($subjects as $subject) {
                while ($day->isWeekend()) {
                    $day->addDay();
                }

                [$hour, $minute] = array_map('intval', explode(':', $slots[$slot]));
                $startsAt = $day->copy()->setTime($hour, $minute);

                $rows[] = [
                    'school_id' => $level->school_id,
                    'term_id' => $term->id,
                    'class_level_id' => $level->id,
                    'subject_id' => $subject->id,
                    'starts_at' => $startsAt,
                    'duration_minutes' => $this->duration($level, $subject->code),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $created++;
                $lastDay = $lastDay === null || $day->gt($lastDay) ? $day->copy() : $lastDay;

                if (++$slot >= count($slots)) {
                    $slot = 0;
                    $day->addDay();
                }
            }
        }

        DB::table('exam_papers')->insert($rows);

        return ['created' => $created, 'last_day' => $lastDay];
    }

    private function duration(ClassLevel $level, string $code): int
    {
        return match (true) {
            in_array($level->section, [ClassSection::Nursery, ClassSection::Primary], true) => 60,
            in_array($code, ['ENG', 'MTH'], true) => 120,
            default => 90,
        };
    }
}
