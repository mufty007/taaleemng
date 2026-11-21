<?php

namespace App\Support\Results;

use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\GradingScheme;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * A class's computed results for one term.
 */
final readonly class ClassResults
{
    /** @var Collection<int, array<string, mixed>> */
    public Collection $students;

    /**
     * @param  Collection<int, AssessmentComponent>  $components
     * @param  Collection<int, ClassSubject>  $classSubjects
     * @param  list<array<string, mixed>>  $studentRows
     * @param  array<int, array{average: float, highest: float, lowest: float, count: int}>  $statistics  by class subject id
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

    public function completion(): int
    {
        return $this->expectedEntries === 0 ? 0 : (int) round($this->enteredEntries / $this->expectedEntries * 100);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forStudent(int $studentId): ?array
    {
        return $this->students->first(fn (array $row) => $row['student']->id === $studentId);
    }
}
