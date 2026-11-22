<?php

namespace App\Support\Results;

use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\GradingScheme;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * A class's computed results for one term.
 *
 * @phpstan-type SubjectResult array{scores: array<int, float>, total: float, max: int, percent: float, complete: bool, grade: string, remark: string, position?: int}
 * @phpstan-type StudentResult array{enrolment: Enrolment, student: Student, subjects: array<int, SubjectResult>, total: float, average: float|null, percent: float|null, subject_count: int, complete: bool, position?: int}
 */
final readonly class ClassResults
{
    /** @var Collection<int, StudentResult> */
    public Collection $students;

    /**
     * @param  Collection<int, AssessmentComponent>  $components
     * @param  Collection<int, ClassSubject>  $classSubjects
     * @param  list<StudentResult>  $studentRows
     * @param  array<int, array{average: float, highest: float, lowest: float, count: int, percent: float}>  $statistics  by class subject id
     */
    public function __construct(
        public ClassArm $arm,
        public Term $term,
        public GradingScheme $scheme,
        public Collection $components,
        public Collection $classSubjects,
        array $studentRows,
        public array $statistics,
        public int $classSize,
        public int $expectedEntries,
        public int $enteredEntries,
    ) {
        $this->students = collect($studentRows);
    }

    /**
     * Whether every score for the term is in, so grades and positions are final.
     */
    public function isComplete(): bool
    {
        return $this->expectedEntries > 0 && $this->enteredEntries >= $this->expectedEntries;
    }

    /**
     * Components with no score at all yet in this class, e.g. the exam mid-term.
     *
     * @return Collection<int, AssessmentComponent>
     */
    public function missingComponents(): Collection
    {
        $entered = [];

        foreach ($this->students as $row) {
            foreach ($row['subjects'] as $cell) {
                $entered += array_fill_keys(array_keys($cell['scores']), true);
            }
        }

        return $this->components->reject(fn (AssessmentComponent $component) => isset($entered[$component->id]))->values();
    }

    public function completion(): int
    {
        return $this->expectedEntries === 0 ? 0 : (int) round($this->enteredEntries / $this->expectedEntries * 100);
    }

    /**
     * @return StudentResult|null
     */
    public function forStudent(int $studentId): ?array
    {
        return $this->students->first(fn (array $row) => $row['student']->id === $studentId);
    }
}
