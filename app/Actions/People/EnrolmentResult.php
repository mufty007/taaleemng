<?php

namespace App\Actions\People;

use App\Models\Guardian;
use App\Models\Student;

/**
 * What enrolling a student produced, including any one-time passwords to hand to the family.
 */
final readonly class EnrolmentResult
{
    public function __construct(
        public Student $student,
        public Guardian $guardian,
        public string $studentPassword,
        public ?string $guardianPassword,
    ) {}
}
