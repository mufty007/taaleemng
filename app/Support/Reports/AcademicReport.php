<?php

namespace App\Support\Reports;

use App\Enums\Gender;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\GradingScheme;
use App\Models\ResultPublication;
use App\Models\Term;
use App\Support\Results\ClassResults;
use App\Support\Results\PromotionRules;
use App\Support\Results\ResultCalculator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A term's results across the whole school: how each class, subject and teacher is
 * doing, the grade spread, the strongest students and those who need help.
 *
 * Every figure is a percentage of the marks entered so far, so the report is useful
 * mid-term (CA only) as well as after exams. "Pass" is the school's promotion pass mark;
 * "credit" is the lowest credit grade on the class's grading scale (C6 on the WAEC scale).
 *
 * @phpstan-type StudentRow array{class: string, name: string, admission_no: string, gender: string, is_male: bool, subjects: int, total: float, average: float|null, percent: float|null, position: int|null, credits: int, failed: int, enough_subjects: bool}
 */
final class AcademicReport implements SchoolReport
{
    /**
     * Students below the pass mark in this many subjects need help even if their average is fine.
     */
    public const AT_RISK_SUBJECTS = 3;

    /** @var Collection<int, ClassResults>|null */
    private ?Collection $results = null;

    private float $passMark;

    /** @var array<int, float> grading scheme id => lowest credit mark */
    private array $creditMarks = [];

    public function __construct(
        public readonly Term $term,
        private readonly ResultCalculator $calculator,
    ) {
        $this->passMark = PromotionRules::passMark();
    }

    public function periodLabel(): string
    {
        return $this->term->name.' · '.$this->term->academicSession->name;
    }

    public function figures(): array
    {
        $results = $this->results();
        $scored = $this->studentRows()->filter(fn (array $row) => $row['percent'] !== null);
        $expected = (int) $results->sum(fn (ClassResults $class) => $class->expectedEntries);
        $entered = (int) $results->sum(fn (ClassResults $class) => $class->enteredEntries);
        $published = ResultPublication::query()->where('term_id', $this->term->id)->pluck('class_arm_id')->flip();
        $cells = (int) $scored->sum('subjects');

        return [
            'term' => $this->term,
            'passMark' => $this->passMark,
            'atRiskSubjects' => self::AT_RISK_SUBJECTS,
            'stats' => [
                'students' => (int) $results->sum(fn (ClassResults $class) => $class->classSize),
                'scored' => $scored->count(),
                'average' => $scored->isEmpty() ? null : round((float) $scored->avg('percent'), 1),
                'pass_rate' => $this->rate($scored->filter(fn (array $row) => $row['percent'] >= $this->passMark)->count(), $scored->count()),
                'credit_rate' => $this->rate((int) $scored->sum('credits'), $cells),
                'completion' => $this->rate($entered, $expected),
                'published' => $results->filter(fn (ClassResults $class) => $published->has($class->arm->id))->count(),
                'classes' => $results->count(),
            ],
            'classes' => $this->classRows($published),
            'subjects' => $this->subjectRows(),
            'teachers' => $this->teacherRows(),
            'grades' => $this->gradeSpread(),
            'genders' => $this->genderRows($this->studentRows()),
            'top' => $scored->filter(fn (array $row) => $row['enough_subjects'])->sortByDesc('percent')->take(10)->values(),
            'atRisk' => $scored
                ->filter(fn (array $row) => $row['percent'] < $this->passMark || $row['failed'] >= self::AT_RISK_SUBJECTS)
                ->sortBy([['percent', 'asc'], ['failed', 'desc']])
                ->values(),
        ];
    }

    public function csv(): StreamedResponse
    {
        $rows = $this->studentRows()->map(fn (array $row) => [
            $row['class'],
            $row['admission_no'],
            $row['name'],
            $row['gender'],
            $row['subjects'],
            $row['total'],
            $row['average'],
            $row['percent'],
            $row['position'] !== null ? ResultCalculator::ordinal($row['position']) : null,
            $row['credits'],
            $row['failed'],
        ]);

        return CsvDownload::make(
            'results-'.str($this->term->name.' '.$this->term->academicSession->name)->slug().'.csv',
            ['Class', 'Admission no.', 'Student', 'Gender', 'Subjects scored', 'Total', 'Average', '% of marks entered', 'Class position', 'Subjects at credit', 'Subjects below pass mark'],
            $rows,
        );
    }

    /**
     * Results for every class arm with students this session, nursery to SS 3.
     *
     * @return Collection<int, ClassResults>
     */
    private function results(): Collection
    {
        if ($this->results !== null) {
            return $this->results;
        }

        $armIds = Enrolment::query()->where('academic_session_id', $this->term->academic_session_id)->distinct()->pluck('class_arm_id');

        return $this->results = ClassArm::query()
            ->with('level')
            ->whereIn('id', $armIds)
            ->get()
            ->sortBy(fn (ClassArm $arm) => EnrolmentReport::armSortKey($arm))
            ->values()
            ->map(fn (ClassArm $arm) => $this->calculator->forClass($arm, $this->term));
    }

    /**
     * The lowest mark that earns a credit grade on a grading scale.
     */
    private function creditMark(GradingScheme $scheme): float
    {
        return $this->creditMarks[$scheme->id] ??= (float) collect($scheme->bands)
            ->filter(fn (array $band) => $scheme->isCredit($band['grade']))
            ->min('min');
    }

    /**
     * One row per student who is in a class this session.
     *
     * @return Collection<int, StudentRow>
     */
    private function studentRows(): Collection
    {
        return $this->results()->flatMap(function (ClassResults $class): array {
            $subjectCount = $class->classSubjects->count();
            $credit = $this->creditMark($class->scheme);

            return $class->students->map(fn (array $row) => [
                'class' => $class->arm->displayName(),
                'name' => $row['student']->registerName(),
                'admission_no' => $row['student']->admission_no,
                'gender' => $row['student']->gender->label(),
                'is_male' => $row['student']->gender === Gender::Male,
                'subjects' => $row['subject_count'],
                'total' => $row['total'],
                'average' => $row['average'],
                'percent' => $row['percent'],
                'position' => $row['position'] ?? null,
                'credits' => count(array_filter($row['subjects'], fn (array $cell) => $cell['percent'] >= $credit)),
                'failed' => count(array_filter($row['subjects'], fn (array $cell) => $cell['percent'] < $this->passMark)),
                // A student scored in one subject so far should not top the school.
                'enough_subjects' => $row['subject_count'] >= max(1, (int) ceil($subjectCount / 2)),
            ])->all();
        })->values();
    }

    /**
     * @param  Collection<int, int>  $published  class arm id => index
     * @return Collection<int, array{arm: ClassArm, students: int, scored: int, average: ?float, highest: ?float, lowest: ?float, pass_rate: ?int, credit_rate: ?int, completion: int, published: bool}>
     */
    private function classRows(Collection $published): Collection
    {
        return $this->results()->map(function (ClassResults $class) use ($published): array {
            $percents = [];
            $cells = [];

            foreach ($class->students as $row) {
                if ($row['percent'] !== null) {
                    $percents[] = $row['percent'];
                }

                foreach ($row['subjects'] as $cell) {
                    $cells[] = $cell['percent'];
                }
            }

            $credit = $this->creditMark($class->scheme);

            return [
                'arm' => $class->arm,
                'students' => $class->classSize,
                'scored' => count($percents),
                'average' => $percents === [] ? null : round(array_sum($percents) / count($percents), 1),
                'highest' => $percents === [] ? null : max($percents),
                'lowest' => $percents === [] ? null : min($percents),
                'pass_rate' => $this->rate(count(array_filter($percents, fn (float $percent) => $percent >= $this->passMark)), count($percents)),
                'credit_rate' => $this->rate(count(array_filter($cells, fn (float $percent) => $percent >= $credit)), count($cells)),
                'completion' => $class->completion(),
                'published' => $published->has($class->arm->id),
            ];
        });
    }

    /**
     * Each subject across every class that takes it, strongest first.
     *
     * @return Collection<int, array{subject: string, classes: int, entries: int, average: float, pass_rate: ?int, credit_rate: ?int, highest: float, lowest: float}>
     */
    private function subjectRows(): Collection
    {
        $names = [];
        $arms = [];
        $percents = [];
        $credits = [];

        foreach ($this->results() as $class) {
            $credit = $this->creditMark($class->scheme);

            foreach ($class->classSubjects as $classSubject) {
                $subjectId = $classSubject->subject_id;

                foreach ($class->students as $row) {
                    $cell = $row['subjects'][$classSubject->id] ?? null;

                    if ($cell === null) {
                        continue;
                    }

                    $names[$subjectId] = $classSubject->subject->name;
                    $arms[$subjectId][$class->arm->id] = true;
                    $percents[$subjectId][] = $cell['percent'];
                    $credits[$subjectId] = ($credits[$subjectId] ?? 0) + ($cell['percent'] >= $credit ? 1 : 0);
                }
            }
        }

        $rows = [];

        foreach ($percents as $subjectId => $scores) {
            $rows[] = [
                'subject' => $names[$subjectId],
                'classes' => count($arms[$subjectId]),
                'entries' => count($scores),
                'average' => round(array_sum($scores) / count($scores), 1),
                'pass_rate' => $this->rate(count(array_filter($scores, fn (float $percent) => $percent >= $this->passMark)), count($scores)),
                'credit_rate' => $this->rate($credits[$subjectId], count($scores)),
                'highest' => max($scores),
                'lowest' => min($scores),
            ];
        }

        return collect($rows)->sortByDesc('average')->values();
    }

    /**
     * Each subject teacher's classes: how their students are doing and how much of the
     * score sheet they have filled in, least complete first.
     *
     * @return Collection<int, array{teacher: string, assignments: int, entries: int, average: ?float, credit_rate: ?int, completion: int}>
     */
    private function teacherRows(): Collection
    {
        // Keyed by teacher (0 for subjects nobody is assigned to).
        $names = [];
        $assignments = [];
        $expected = [];
        $entered = [];
        $credits = [];
        $percents = [];

        foreach ($this->results() as $class) {
            $componentCount = $class->components->count();
            $credit = $this->creditMark($class->scheme);

            foreach ($class->classSubjects as $classSubject) {
                $key = $classSubject->teacher_id ?? 0;
                $names[$key] ??= $classSubject->teacher?->fullName() ?? 'No teacher assigned';
                $assignments[$key] = ($assignments[$key] ?? 0) + 1;
                $expected[$key] = ($expected[$key] ?? 0) + $class->classSize * $componentCount;
                $entered[$key] ??= 0;
                $credits[$key] ??= 0;
                $percents[$key] ??= [];

                foreach ($class->students as $row) {
                    $cell = $row['subjects'][$classSubject->id] ?? null;

                    if ($cell !== null) {
                        $entered[$key] += count($cell['scores']);
                        $percents[$key][] = $cell['percent'];
                        $credits[$key] += $cell['percent'] >= $credit ? 1 : 0;
                    }
                }
            }
        }

        $rows = [];

        foreach ($names as $key => $name) {
            $rows[] = [
                'teacher' => $name,
                'assignments' => $assignments[$key],
                'entries' => count($percents[$key]),
                'average' => $percents[$key] === [] ? null : round(array_sum($percents[$key]) / count($percents[$key]), 1),
                'credit_rate' => $this->rate($credits[$key], count($percents[$key])),
                'completion' => $this->rate($entered[$key], $expected[$key]) ?? 0,
            ];
        }

        return collect($rows)
            ->sortBy([['completion', 'asc'], ['teacher', 'asc']])
            ->values();
    }

    /**
     * Every subject score graded on its class's scale, using the marks entered so far,
     * one spread per grading scale (e.g. A–F for primary, A1–F9 for secondary).
     *
     * @return array<string, array<string, int>> scale name => grade => subject scores
     */
    private function gradeSpread(): array
    {
        $spread = [];

        foreach ($this->results() as $class) {
            $name = $class->scheme->name;
            $spread[$name] ??= array_fill_keys(array_column($class->scheme->bands, 'grade'), 0);

            foreach ($class->students as $row) {
                foreach ($row['subjects'] as $cell) {
                    $spread[$name][$class->scheme->gradeFor($cell['percent'])['grade']]++;
                }
            }
        }

        return array_filter($spread, fn (array $grades) => array_sum($grades) > 0);
    }

    /**
     * Boys against girls, among students scored so far.
     *
     * @param  Collection<int, StudentRow>  $students
     * @return list<array{label: string, students: int, average: ?float, pass_rate: ?int}>
     */
    private function genderRows(Collection $students): array
    {
        return collect(['Boys' => true, 'Girls' => false])
            ->map(function (bool $isMale, string $label) use ($students): array {
                $group = $students->where('is_male', $isMale)->filter(fn (array $row) => $row['percent'] !== null);

                return [
                    'label' => $label,
                    'students' => $group->count(),
                    'average' => $group->isEmpty() ? null : round((float) $group->avg('percent'), 1),
                    'pass_rate' => $this->rate($group->filter(fn (array $row) => $row['percent'] >= $this->passMark)->count(), $group->count()),
                ];
            })
            ->values()
            ->all();
    }

    private function rate(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : (int) round($part / $whole * 100);
    }
}
