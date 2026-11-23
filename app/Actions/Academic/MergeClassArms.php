<?php

namespace App\Actions\Academic;

use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Quiz;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collapse lettered arms of a class level into one class per stream
 * (or one class when there is no stream). Science / Arts / Commercial stay separate.
 */
class MergeClassArms
{
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
                    Enrolment::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);
                    AttendanceRecord::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);

                    foreach ($arm->classSubjects as $subject) {
                        $targetId = ClassSubject::query()
                            ->where('class_arm_id', $keep->id)
                            ->where('subject_id', $subject->subject_id)
                            ->value('id');

                        if ($targetId) {
                            Assignment::query()->where('class_subject_id', $subject->id)->update(['class_subject_id' => $targetId]);
                            Quiz::query()->where('class_subject_id', $subject->id)->update(['class_subject_id' => $targetId]);
                            TimetableEntry::query()->where('class_subject_id', $subject->id)->delete();
                            $subject->delete();
                        } else {
                            $subject->update(['class_arm_id' => $keep->id]);
                        }
                    }

                    TimetableEntry::query()->where('class_arm_id', $arm->id)->update(['class_arm_id' => $keep->id]);

                    if ($keep->form_teacher_id === null && $arm->form_teacher_id !== null) {
                        $keep->update(['form_teacher_id' => $arm->form_teacher_id]);
                    }

                    $arm->delete();
                }

                $keep->loadMissing('stream');
                $keep->update(['name' => $keep->stream?->name ?? '']);
                $kept->push($keep->fresh(['level', 'stream', 'formTeacher']));
            }

            activity('academics')->performedOn($level)->log("merged {$level->name} into single classes per stream");

            return $kept;
        });
    }
}
