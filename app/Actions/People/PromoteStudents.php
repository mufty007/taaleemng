<?php

namespace App\Actions\People;

use App\Enums\EnrolmentStatus;
use App\Enums\PromotionDecision;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Term;
use App\Models\TermReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves one class into the next session at the end of the year. Promoted
 * students (and those on probation) go up a class, repeaters stay in their
 * class, graduates leave the school with their history kept.
 *
 * Safe to run again: a student already placed in the next session is moved,
 * not enrolled twice, and the decision on their third-term report is kept in step.
 */
class PromoteStudents
{
    /**
     * @param  array<int, array{decision: PromotionDecision, class_arm_id: int|null}>  $placements  by enrolment id in the class
     * @return array{up: int, repeat: int, graduated: int}
     */
    public function handle(ClassArm $classArm, AcademicSession $from, AcademicSession $to, array $placements, User $by): array
    {
        if ($to->starts_on->lte($from->starts_on)) {
            throw new InvalidArgumentException('Students can only be moved into a later session.');
        }

        $finalTerm = Term::query()->where('academic_session_id', $from->id)->orderByDesc('sequence')->firstOrFail();
        $counts = ['up' => 0, 'repeat' => 0, 'graduated' => 0];

        $enrolments = Enrolment::query()
            ->with('student')
            ->where('academic_session_id', $from->id)
            ->where('class_arm_id', $classArm->id)
            ->whereIn('id', array_keys($placements))
            ->get();

        DB::transaction(function () use ($enrolments, $placements, $to, $finalTerm, $by, &$counts): void {
            foreach ($enrolments as $enrolment) {
                ['decision' => $decision, 'class_arm_id' => $targetArmId] = $placements[$enrolment->id];
                $student = $enrolment->student;

                if ($decision === PromotionDecision::Graduated) {
                    Enrolment::query()->where('student_id', $student->id)->where('academic_session_id', $to->id)->delete();
                    $enrolment->update(['status' => EnrolmentStatus::Graduated]);
                    $student->update(['status' => StudentStatus::Graduated]);
                    $counts['graduated']++;
                } else {
                    Enrolment::query()->updateOrCreate(
                        ['student_id' => $student->id, 'academic_session_id' => $to->id],
                        ['class_arm_id' => $targetArmId, 'status' => EnrolmentStatus::Active],
                    );
                    $enrolment->update(['status' => $decision->movesUp() ? EnrolmentStatus::Promoted : EnrolmentStatus::Repeated]);

                    if ($student->status === StudentStatus::Graduated) {
                        $student->update(['status' => StudentStatus::Active]);
                    }

                    $counts[$decision->movesUp() ? 'up' : 'repeat']++;
                }

                TermReport::query()->updateOrCreate(
                    ['term_id' => $finalTerm->id, 'enrolment_id' => $enrolment->id],
                    ['promotion_decision' => $decision, 'updated_by' => $by->id],
                );
            }
        });

        activity('academics')->performedOn($classArm)->withProperties($counts)
            ->log("moved {$classArm->displayName()} into the {$to->name} session");

        return $counts;
    }
}
