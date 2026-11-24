<?php

namespace App\Actions\Academic;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Quiz;
use App\Models\ResultPublication;
use App\Models\Score;
use App\Models\SpecialExamCandidate;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collapse lettered arms of a class level into one class per stream
 * (or one class when there is no stream). Science / Arts / Commercial stay separate.
 *
 * Nothing a teacher entered is lost: students, attendance, scores, classwork,
 * notices, exam candidates and published results all move to the class that stays.
 * Only timetable lessons that clash with the kept class's own lessons are dropped.
 */
class MergeClassArms
{
    /**
     * @return Collection<int, ClassArm>
     */
    public function handle(ClassLevel $level): Collection
    {
        $arms = $level->arms()->with('classSubjects')->orderBy('name')->get();

        if ($arms->isEmpty()) {
            throw ValidationException::withMessages(['class_structure' => "{$level->name} has no classes to merge."]);
        }

        return DB::transaction(function () use ($level, $arms): Collection {
            $kept = collect();

            foreach ($arms->groupBy(fn (ClassArm $arm) => $arm->stream_id ?? 'none') as $group) {
                $keep = $group->first();

                foreach ($group->where('id', '!=', $keep->id) as $arm) {
                    $this->moveInto($arm, $keep);
                }

                $keep->loadMissing('stream');
                $keep->update(['name' => $keep->stream_id === null ? '' : $keep->stream->name]);
                $kept->push($keep->fresh(['level', 'stream', 'formTeacher']));
            }

            activity('academics')->performedOn($level)->log("merged {$level->name} into single classes per stream");

            return $kept;
        });
    }

    private function moveInto(ClassArm $arm, ClassArm $keep): void
    {
        // Lessons in a slot the kept class already uses are dropped; the rest move across.
        $takenSlots = TimetableEntry::query()->where('class_arm_id', $keep->id)
            ->get(['day', 'period_id'])
            ->map(fn (TimetableEntry $entry) => $entry->day.'-'.$entry->period_id);

        TimetableEntry::query()->where('class_arm_id', $arm->id)->get()
            ->each(function (TimetableEntry $entry) use ($takenSlots, $keep): void {
                $takenSlots->contains($entry->day.'-'.$entry->period_id)
                    ? $entry->delete()
                    : $entry->update(['class_arm_id' => $keep->id]);
            });

        foreach ($arm->classSubjects as $subject) {
            $target = ClassSubject::query()
                ->where('class_arm_id', $keep->id)
                ->where('subject_id', $subject->subject_id)
                ->first();

            if ($target === null) {
                $subject->update(['class_arm_id' => $keep->id]);

                continue;
            }

            // Same subject in both classes: everything recorded against it joins the kept one.
            Score::query()->where('class_subject_id', $subject->id)->update(['class_subject_id' => $target->id]);
            Assignment::query()->where('class_subject_id', $subject->id)->update(['class_subject_id' => $target->id]);
            Quiz::query()->where('class_subject_id', $subject->id)->update(['class_subject_id' => $target->id]);
            TimetableEntry::query()->where('class_subject_id', $subject->id)->update(['class_subject_id' => $target->id]);

            if ($target->teacher_id === null && $subject->teacher_id !== null) {
                $target->update(['teacher_id' => $subject->teacher_id]);
            }

            $subject->delete();
        }

        Enrolment::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);
        AttendanceRecord::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);
        Announcement::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);
        SpecialExamCandidate::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);

        $publishedTerms = ResultPublication::query()->where('class_arm_id', $keep->id)->pluck('term_id');
        ResultPublication::query()->where('class_arm_id', $arm->id)->whereIn('term_id', $publishedTerms)->delete();
        ResultPublication::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);

        $keep->update([
            'form_teacher_id' => $keep->form_teacher_id ?? $arm->form_teacher_id,
            'capacity' => $keep->capacity !== null && $arm->capacity !== null ? $keep->capacity + $arm->capacity : $keep->capacity,
        ]);

        $arm->delete();
    }
}
