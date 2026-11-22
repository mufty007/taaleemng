<?php

namespace App\Support\Exams;

use App\Models\SpecialExam;
use App\Models\SpecialExamCandidate;
use App\Models\SpecialExamPaper;
use App\Models\SpecialExamScore;
use App\Models\Student;
use App\Support\Results\ResultCalculator;
use Illuminate\Support\Collection;

/**
 * A special exam's results: each candidate's marks, total, percentage of the whole exam,
 * pass or fail against the exam's pass mark, and position; and how each paper went.
 *
 * A candidate with no marks at all has not sat the exam and is left out of the ranking.
 * Positions use competition ranking ("1224"), the same as termly results.
 *
 * @phpstan-type CandidateResult array{candidate: SpecialExamCandidate, student: Student, class: string, scores: array<int, float>, total: float, sat: int, percent: float|null, passed: bool|null, position: int|null}
 * @phpstan-type PaperResult array{paper: SpecialExamPaper, sat: int, average: float|null, highest: float|null, lowest: float|null, pass_rate: int|null}
 */
final readonly class SpecialExamResults
{
    /**
     * @param  Collection<int, SpecialExamPaper>  $papers
     * @param  Collection<int, CandidateResult>  $candidates  best first, then those yet to sit
     * @param  Collection<int, PaperResult>  $paperResults
     */
    public function __construct(
        public SpecialExam $exam,
        public Collection $papers,
        public Collection $candidates,
        public Collection $paperResults,
        public int $maxTotal,
    ) {}

    public static function for(SpecialExam $exam): self
    {
        $papers = $exam->papers()->with('subject')->get();
        $maxTotal = (int) $papers->sum('max_score');

        $candidates = $exam->candidates()
            ->whereHas('student')
            ->with(['student', 'classArm.level', 'scores'])
            ->get();

        // Marks by candidate and paper, then positions among those who sat.
        $marks = [];
        $totals = [];

        foreach ($candidates as $candidate) {
            $marks[$candidate->id] = $candidate->scores->mapWithKeys(fn (SpecialExamScore $score) => [$score->special_exam_paper_id => $score->score])->all();

            if ($marks[$candidate->id] !== []) {
                $totals[$candidate->id] = round(array_sum($marks[$candidate->id]), 1);
            }
        }

        $positions = ResultCalculator::rank($totals);
        $rows = [];

        foreach ($candidates as $candidate) {
            if ($candidate->student === null) {
                continue;
            }

            $scores = $marks[$candidate->id];
            $total = $totals[$candidate->id] ?? 0.0;
            $percent = $scores === [] || $maxTotal === 0 ? null : round($total / $maxTotal * 100, 1);

            $rows[] = [
                'candidate' => $candidate,
                'student' => $candidate->student,
                'class' => $candidate->classArm?->displayName() ?? '—',
                'scores' => $scores,
                'total' => $total,
                'sat' => count($scores),
                'percent' => $percent,
                'passed' => $percent === null ? null : $percent >= $exam->pass_mark,
                'position' => $positions[$candidate->id] ?? null,
            ];
        }

        $ranked = collect($rows)->sortBy([
            fn (array $a, array $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX),
            fn (array $a, array $b) => $a['student']->last_name.$a['student']->first_name <=> $b['student']->last_name.$b['student']->first_name,
        ])->values();

        return new self($exam, $papers, $ranked, self::paperResults($papers, $ranked, $exam->pass_mark), $maxTotal);
    }

    /**
     * @return array{candidates: int, sat: int, average: float|null, pass_rate: int|null, passed: int, highest: float|null}
     */
    public function summary(): array
    {
        $sat = $this->candidates->filter(fn (array $row) => $row['sat'] > 0);
        $passed = $sat->filter(fn (array $row) => $row['passed'] === true)->count();

        return [
            'candidates' => $this->candidates->count(),
            'sat' => $sat->count(),
            'average' => $sat->isEmpty() ? null : round((float) $sat->avg('percent'), 1),
            'pass_rate' => $sat->isEmpty() ? null : (int) round($passed / $sat->count() * 100),
            'passed' => $passed,
            'highest' => $sat->isEmpty() ? null : (float) $sat->max('total'),
        ];
    }

    /**
     * @return CandidateResult|null
     */
    public function forStudent(int $studentId): ?array
    {
        return $this->candidates->first(fn (array $row) => $row['student']->id === $studentId);
    }

    /**
     * @param  Collection<int, SpecialExamPaper>  $papers
     * @param  Collection<int, CandidateResult>  $candidates
     * @return Collection<int, PaperResult>
     */
    private static function paperResults(Collection $papers, Collection $candidates, int $passMark): Collection
    {
        return $papers->map(function (SpecialExamPaper $paper) use ($candidates, $passMark): array {
            $marks = [];

            foreach ($candidates as $row) {
                if (isset($row['scores'][$paper->id])) {
                    $marks[] = $row['scores'][$paper->id];
                }
            }

            $passes = count(array_filter($marks, fn (float $mark) => $paper->max_score > 0 && $mark / $paper->max_score * 100 >= $passMark));

            return [
                'paper' => $paper,
                'sat' => count($marks),
                'average' => $marks === [] ? null : round(array_sum($marks) / count($marks), 1),
                'highest' => $marks === [] ? null : max($marks),
                'lowest' => $marks === [] ? null : min($marks),
                'pass_rate' => $marks === [] ? null : (int) round($passes / count($marks) * 100),
            ];
        });
    }
}
