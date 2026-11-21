<?php

namespace App\Actions\Admissions;

use App\Actions\People\EnrolmentResult;
use App\Actions\People\EnrolStudent;
use App\Enums\ApplicantStatus;
use App\Models\Applicant;
use App\Models\ClassArm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turn an offered applicant into a student: admission number, a place in a
 * class for the session they applied for, and logins for them and their parent.
 */
class EnrolApplicant
{
    public function __construct(private EnrolStudent $enrolStudent) {}

    public function handle(Applicant $applicant, ClassArm $classArm): EnrolmentResult
    {
        if (! $applicant->status->canEnrol()) {
            throw ValidationException::withMessages(['class_arm_id' => 'Offer the applicant a place before enrolling them.']);
        }

        if ($classArm->class_level_id !== $applicant->class_level_id) {
            throw ValidationException::withMessages(['class_arm_id' => 'Choose a class in '.$applicant->classLevel->name.', the class they applied for.']);
        }

        return DB::transaction(function () use ($applicant, $classArm): EnrolmentResult {
            $result = $this->enrolStudent->handle(
                [...array_filter($applicant->only(Applicant::STUDENT_FIELDS), fn ($value) => $value !== null), 'gender' => $applicant->gender->value, 'date_of_birth' => $applicant->date_of_birth?->toDateString()],
                $classArm,
                [
                    'title' => $applicant->guardian_title,
                    'first_name' => $applicant->guardian_first_name,
                    'last_name' => $applicant->guardian_last_name,
                    'relationship' => $applicant->guardian_relationship,
                    'phone' => $applicant->guardian_phone,
                    'email' => $applicant->guardian_email,
                    'occupation' => $applicant->guardian_occupation,
                ],
                session: $applicant->academicSession,
            );

            $applicant->update(['status' => ApplicantStatus::Enrolled, 'student_id' => $result->student->id]);
            activity('admissions')->performedOn($applicant)->log("enrolled applicant {$applicant->application_no} as {$result->student->admission_no}");

            return $result;
        });
    }
}
