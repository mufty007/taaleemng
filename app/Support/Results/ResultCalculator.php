<?php

namespace App\Support\Results;

use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\GradingScheme;
use App\Models\Score;
use App\Models\Term;

/**
 * Turns raw scores into a class's results for a term: subject totals, grades,
 * subject positions, each student's average and class position, and subject statistics.
 *
 * Positions use competition ranking ("1224"): equal scores share a position.
 *
 * Until every component is in (usually the exam), a subject is "so far": its total is out of
 * the marks entered so far, and the percentage is what report cards show instead of a grade.
 */
class ResultCalculator
{
    public function forClass(ClassArm $arm, Term $term): ClassResults
    {
        $arm->loadMissing(['level.gradingScheme', 'classSubjects.subject', 'classSubjects.teacher']);

        $components = AssessmentComponent::query()->orderBy('position')->get();
        $scheme = $arm->level->gradingScheme ?? GradingScheme::query()->firstOrFail();
        $classSubjects = $arm->classSubjects->sortBy('subject.name')->values();

        $enrolments = Enrolment::query()
            ->where('class_arm_id', $arm->id)
            ->where('academic_session_id', $term->academic_session_id)
            ->with('student')
            ->get()
            ->sortBy(fn (Enrolment $enrolment) => $enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->values();

        // [enrolment id][class subject id][component id] => score
        $raw = [];
        Score::query()
            ->where('term_id', $term->id)
            ->whereIn('enrolment_id', $enrolments->pluck('id'))
            ->get(['enrolment_id', 'class_subject_id', 'assessment_component_id', 'score'])
            ->each(function (Score $score) use (&$raw): void {
                $raw[$score->enrolment_id][$score->class_subject_id][$score->assessment_component_id] = $score->score;
            });

        $componentCount = $components->count();
        $maxScores = $components->pluck('max_score', 'id')->all();
        $subjectPercents = [];
        $students = [];
        $subjectTotals = [];

        foreach ($enrolments as $enrolment) {
            $subjects = [];

            foreach ($classSubjects as $classSubject) {
                $entered = $raw[$enrolment->id][$classSubject->id] ?? [];

                if ($entered === []) {
                    continue;
                }

                $total = round(array_sum($entered), 1);
                $max = (int) array_sum(array_intersect_key($maxScores, $entered));
                $percent = $max > 0 ? round($total / $max * 100, 1) : 0.0;
                $subjects[$classSubject->id] = [
                    'scores' => $entered,
                    'total' => $total,
                    'max' => $max,
                    'percent' => $percent,
                    'complete' => count($entered) === $componentCount,
                    ...$scheme->gradeFor($total),
                ];
                $subjectTotals[$classSubject->id][$enrolment->id] = $total;
                $subjectPercents[$classSubject->id][$enrolment->id] = $percent;
            }

            $sum = array_sum(array_column($subjects, 'total'));

            $students[$enrolment->id] = [
                'enrolment' => $enrolment,
                'student' => $enrolment->student,
                'subjects' => $subjects,
                'total' => round($sum, 1),
                'average' => $subjects === [] ? null : round($sum / count($subjects), 2),
                'percent' => $subjects === [] ? null : round(array_sum(array_column($subjects, 'percent')) / count($subjects), 1),
                'subject_count' => count($subjects),
                // Every component of every subject entered, so the average is final.
                'complete' => $subjects !== [] && array_all($subjects, fn (array $subject) => $subject['complete']),
            ];
        }

        foreach ($subjectTotals as $classSubjectId => $totals) {
            foreach (self::rank($totals) as $enrolmentId => $position) {
                $students[$enrolmentId]['subjects'][$classSubjectId]['position'] = $position;
            }
        }

        $averages = array_filter(array_map(fn (array $row) => $row['average'], $students), fn ($average) => $average !== null);

        foreach (self::rank($averages) as $enrolmentId => $position) {
            $students[$enrolmentId]['position'] = $position;
        }

        $statistics = collect($subjectTotals)->map(fn (array $totals, int $classSubjectId) => [
            'average' => round(array_sum($totals) / count($totals), 1),
            'highest' => max($totals),
            'lowest' => min($totals),
            'count' => count($totals),
            'percent' => round(array_sum($subjectPercents[$classSubjectId]) / count($subjectPercents[$classSubjectId]), 1),
        ])->all();

        return new ClassResults(
            arm: $arm,
            term: $term,
            scheme: $scheme,
            components: $components,
            classSubjects: $classSubjects,
            studentRows: array_values($students),
            statistics: $statistics,
            classSize: $enrolments->count(),
            expectedEntries: $enrolments->count() * $classSubjects->count() * $componentCount,
            enteredEntries: array_sum(array_map(fn (array $bySubject) => array_sum(array_map('count', $bySubject)), $raw)),
        );
    }

    /**
     * The session so far for a class, for the third-term report: each student's
     * average and subject totals in every term up to this one, their cumulative
     * average (the mean of their term averages) and their cumulative position.
     *
     * @return array<int, array{terms: array<int, float|null>, subjects: array<int, array<int, float>>, average: float|null, position?: int}> by enrolment id
     */
    public function sessionSoFar(ClassArm $arm, Term $term, ?ClassResults $current = null): array
    {
        $terms = Term::query()
            ->where('academic_session_id', $term->academic_session_id)
            ->where('sequence', '<=', $term->sequence)
            ->orderBy('sequence')
            ->get();

        $summary = [];

        foreach ($terms as $sessionTerm) {
            $results = $current !== null && $sessionTerm->is($term) ? $current : $this->forClass($arm, $sessionTerm);

            foreach ($results->students as $row) {
                $enrolmentId = $row['enrolment']->id;
                $summary[$enrolmentId]['terms'][$sessionTerm->sequence] = $row['average'];
                $summary[$enrolmentId]['subjects'] ??= [];

                foreach ($row['subjects'] as $classSubjectId => $cell) {
                    $summary[$enrolmentId]['subjects'][$classSubjectId][$sessionTerm->sequence] = $cell['total'];
                }
            }
        }

        foreach ($summary as $enrolmentId => $student) {
            $averages = array_filter($student['terms'], fn (?float $average) => $average !== null);
            $summary[$enrolmentId]['average'] = $averages === [] ? null : round(array_sum($averages) / count($averages), 2);
        }

        $cumulative = array_filter(array_map(fn (array $student) => $student['average'], $summary), fn (?float $average) => $average !== null);

        foreach (self::rank($cumulative) as $enrolmentId => $position) {
            $summary[$enrolmentId]['position'] = $position;
        }

        return $summary;
    }

    /**
     * Competition ranking: highest first, ties share a position, the next position skips.
     *
     * @param  array<int, float>  $values  key => value
     * @return array<int, int> key => position
     */
    public static function rank(array $values): array
    {
        arsort($values);
        $positions = [];
        $position = 0;
        $previous = null;

        foreach (array_keys($values) as $index => $key) {
            if ($previous === null || $values[$key] < $previous) {
                $position = $index + 1;
            }

            $positions[$key] = $position;
            $previous = $values[$key];
        }

        return $positions;
    }

    public static function ordinal(int $number): string
    {
        $tens = $number % 100;

        if ($tens >= 11 && $tens <= 13) {
            return $number.'th';
        }

        return $number.(match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        });
    }
}
