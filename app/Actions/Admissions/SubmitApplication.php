<?php

namespace App\Actions\Admissions;

use App\Enums\ApplicantStatus;
use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\User;
use App\Support\Numbering\SequenceGenerator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Record an application, from the school's public form or typed in at the office.
 */
class SubmitApplication
{
    public function __construct(private SequenceGenerator $sequences) {}

    /**
     * @param  array<string, mixed>  $data  validated by ApplicationRules
     * @param  string  $source  'online' or 'office'
     */
    public function handle(array $data, string $source): Applicant
    {
        $session = AcademicSession::forAdmissions()
            ?? throw ValidationException::withMessages(['class_level_id' => 'The school is not taking applications yet.']);

        $applicant = Applicant::query()->create([
            ...$data,
            'guardian_phone' => User::normalisePhone($data['guardian_phone']) ?? $data['guardian_phone'],
            'guardian_email' => filled($data['guardian_email'] ?? null) ? Str::lower($data['guardian_email']) : null,
            'application_no' => $this->sequences->next('application_no'),
            'academic_session_id' => $session->id,
            'status' => ApplicantStatus::Submitted,
            'source' => $source,
        ]);

        activity('admissions')->performedOn($applicant)->log("received application {$applicant->application_no} for {$applicant->fullName()}");

        return $applicant;
    }
}
