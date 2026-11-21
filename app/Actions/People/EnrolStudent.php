<?php

namespace App\Actions\People;

use App\Actions\Platform\AddSchoolMember;
use App\Enums\EnrolmentStatus;
use App\Enums\SchoolRole;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Support\Numbering\SequenceGenerator;
use App\Support\TemporaryPassword;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admit a student into a class for the current session, link (or reuse) their
 * guardian, and give both of them a login.
 *
 * Guardians are matched by phone number, so siblings share one parent account.
 */
class EnrolStudent
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private SequenceGenerator $sequences,
        private AddSchoolMember $addSchoolMember,
    ) {}

    /**
     * @param  array{admission_no?: ?string, first_name: string, middle_name?: ?string, last_name: string, gender: string, date_of_birth?: ?string, state_of_origin?: ?string, lga?: ?string, religion?: ?string, blood_group?: ?string, genotype?: ?string, address?: ?string, previous_school?: ?string, medical_notes?: ?string, admitted_on?: ?string}  $student
     * @param  array{title?: ?string, first_name: string, last_name: string, relationship: string, phone: string, email?: ?string, occupation?: ?string}  $guardian
     * @param  string|null  $password  Fixed password for both new logins (demo seeding); a readable one is generated otherwise.
     * @param  AcademicSession|null  $session  The session to enrol into; the current one unless an applicant was admitted for the next.
     */
    public function handle(array $student, ClassArm $classArm, array $guardian, ?string $password = null, ?AcademicSession $session = null): EnrolmentResult
    {
        $school = $this->currentSchool->getOrFail();
        $session ??= AcademicSession::current()
            ?? throw ValidationException::withMessages(['class_arm_id' => 'Set up the current session before enrolling students.']);

        return DB::transaction(function () use ($school, $session, $student, $classArm, $guardian, $password): EnrolmentResult {
            $admittedOn = isset($student['admitted_on']) ? Carbon::parse($student['admitted_on']) : now();

            $record = Student::query()->create([
                ...$student,
                'admission_no' => filled($student['admission_no'] ?? null) ? $student['admission_no'] : $this->nextAdmissionNumber($admittedOn),
                'admitted_on' => $admittedOn,
                'status' => StudentStatus::Active,
            ]);

            $record->enrolments()->create([
                'academic_session_id' => $session->id,
                'class_arm_id' => $classArm->id,
                'status' => EnrolmentStatus::Active,
            ]);

            $studentPassword = $password ?? TemporaryPassword::generate();
            $studentUser = User::query()->create([
                'name' => trim($record->first_name.' '.$record->last_name),
                'password' => $studentPassword,
            ]);
            $record->update(['user_id' => $studentUser->id]);
            $this->addSchoolMember->handle($school, $studentUser, SchoolRole::Student);

            [$guardianRecord, $guardianPassword] = $this->linkGuardian($record, $guardian, $password);

            activity('people')->performedOn($record)->log("enrolled {$record->fullName()} ({$record->admission_no})");

            return new EnrolmentResult($record, $guardianRecord, $studentPassword, $guardianPassword);
        });
    }

    /**
     * The next number in the school's pattern, skipping any already taken by an
     * imported student who kept the number from the school's old records.
     */
    private function nextAdmissionNumber(Carbon $admittedOn): string
    {
        do {
            $number = $this->sequences->next('admission_no', $admittedOn);
        } while (Student::query()->where('admission_no', $number)->exists());

        return $number;
    }

    /**
     * @param  array{title?: ?string, first_name: string, last_name: string, relationship: string, phone: string, email?: ?string, occupation?: ?string}  $data
     * @return array{0: Guardian, 1: ?string}
     */
    private function linkGuardian(Student $student, array $data, ?string $password): array
    {
        $phone = User::normalisePhone($data['phone']) ?? $data['phone'];
        $email = filled($data['email'] ?? null) ? Str::lower($data['email']) : null;

        $guardian = Guardian::query()->firstOrCreate(['phone' => $phone], [...$data, 'phone' => $phone, 'email' => $email]);
        $isFirstGuardian = ! $student->guardians()->exists();
        $student->guardians()->attach($guardian->id, ['is_primary' => $isFirstGuardian]);

        $newPassword = null;

        if ($guardian->user_id === null) {
            // A parent may already have an account from another school on Ta'aleem.
            $user = User::query()->where('phone', $phone)->first()
                ?? ($email ? User::query()->where('email', $email)->first() : null);

            if ($user === null) {
                $newPassword = $password ?? TemporaryPassword::generate();
                $user = User::query()->create([
                    'name' => trim($guardian->first_name.' '.$guardian->last_name),
                    'phone' => $phone,
                    'email' => $email,
                    'password' => $newPassword,
                ]);
            }

            $guardian->update(['user_id' => $user->id]);
            $this->addSchoolMember->handle($this->currentSchool->getOrFail(), $user, SchoolRole::Parent);
        }

        return [$guardian, $newPassword];
    }
}
