<?php

namespace App\Support\Reports;

use App\Enums\ClassSection;
use App\Enums\EnrolmentStatus;
use App\Enums\Gender;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Guardian;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who is on roll in a session: class by class, boys and girls, new intake against
 * returning students, class capacity, and the make-up of the student body.
 *
 * "On roll" is every enrolment in the session except withdrawals, the same rule invoicing uses.
 */
final class EnrolmentReport implements SchoolReport
{
    /** @var EloquentCollection<int, Enrolment>|null */
    private ?EloquentCollection $enrolments = null;

    /** @var array<int, true>|null student id => true */
    private ?array $returning = null;

    public function __construct(public readonly AcademicSession $session) {}

    public function periodLabel(): string
    {
        return $this->session->name.' session';
    }

    public function figures(): array
    {
        $all = $this->enrolments();
        $onRoll = $all->reject(fn (Enrolment $enrolment) => $enrolment->status === EnrolmentStatus::Withdrawn);
        $classes = $this->classes($onRoll);
        $withCapacity = $classes->filter(fn (array $row) => $row['capacity'] !== null);
        $capacity = (int) $withCapacity->sum('capacity');

        return [
            'session' => $this->session,
            'stats' => [
                'on_roll' => $onRoll->count(),
                'boys' => $this->countGender($onRoll, Gender::Male),
                'girls' => $this->countGender($onRoll, Gender::Female),
                'new' => $onRoll->reject(fn (Enrolment $enrolment) => isset($this->returning()[$enrolment->student_id]))->count(),
                'withdrawn' => $all->count() - $onRoll->count(),
                'arms' => $classes->count(),
                'average_class' => $classes->isEmpty() ? null : (int) round($onRoll->count() / $classes->count()),
                'capacity' => $capacity,
                'seats_filled' => $capacity > 0 ? (int) round($withCapacity->sum('total') / $capacity * 100) : null,
            ],
            'sections' => $this->sections($classes, $onRoll->count()),
            'classes' => $classes,
            'ages' => $this->ages($onRoll),
            'religions' => $this->breakdown($onRoll, fn (Enrolment $enrolment) => $enrolment->student->religion, 6),
            'states' => $this->breakdown($onRoll, fn (Enrolment $enrolment) => $enrolment->student->state_of_origin, 8),
            'statuses' => $all->countBy(fn (Enrolment $enrolment) => $enrolment->status->label())->sortDesc()->all(),
            'trend' => $this->trend(),
        ];
    }

    public function csv(): StreamedResponse
    {
        $enrolments = $this->enrolments()
            ->reject(fn (Enrolment $enrolment) => $enrolment->status === EnrolmentStatus::Withdrawn)
            ->load(['student.guardians' => fn ($query) => $query->wherePivot('is_primary', true)]);

        $rows = $enrolments
            ->sortBy(fn (Enrolment $enrolment) => self::armSortKey($enrolment->classArm).' '.$enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->map(function (Enrolment $enrolment): array {
                $student = $enrolment->student;
                /** @var Guardian|null $guardian */
                $guardian = $student->guardians->first();

                return [
                    $enrolment->classArm->displayName(),
                    $student->admission_no,
                    $student->last_name,
                    $student->first_name,
                    $student->middle_name,
                    $student->gender->label(),
                    $student->date_of_birth?->format('Y-m-d'),
                    $student->age(),
                    $student->state_of_origin,
                    $student->religion,
                    isset($this->returning()[$student->id]) ? 'Returning' : 'New',
                    $enrolment->status->label(),
                    $guardian?->fullName(),
                    $guardian?->phone,
                ];
            });

        return CsvDownload::make(
            'enrolment-'.str_replace('/', '-', $this->session->name).'.csv',
            ['Class', 'Admission no.', 'Surname', 'First name', 'Other names', 'Gender', 'Date of birth', 'Age', 'State of origin', 'Religion', 'Intake', 'Status', 'Parent / guardian', 'Phone'],
            $rows,
        );
    }

    /**
     * Sorts class arms nursery to SS 3, then by arm name.
     */
    public static function armSortKey(ClassArm $arm): string
    {
        return sprintf('%03d-%s', $arm->level->position, $arm->name);
    }

    /**
     * @return EloquentCollection<int, Enrolment>
     */
    private function enrolments(): EloquentCollection
    {
        return $this->enrolments ??= Enrolment::query()
            ->where('academic_session_id', $this->session->id)
            ->whereHas('student')
            ->with(['student', 'classArm.level'])
            ->get();
    }

    /**
     * Students who were enrolled in an earlier session.
     *
     * @return array<int, true>
     */
    private function returning(): array
    {
        if ($this->returning !== null) {
            return $this->returning;
        }

        $earlierSessions = AcademicSession::query()->where('starts_on', '<', $this->session->starts_on)->pluck('id');

        return $this->returning = $earlierSessions->isEmpty() ? [] : array_fill_keys(Enrolment::query()
            ->whereIn('academic_session_id', $earlierSessions)
            ->whereIn('student_id', $this->enrolments()->pluck('student_id'))
            ->pluck('student_id')
            ->all(), true);
    }

    /**
     * @param  Collection<int, Enrolment>  $onRoll
     * @return Collection<int, array{arm: ClassArm, section: ClassSection, form_teacher: ?string, capacity: ?int, boys: int, girls: int, total: int, new: int, fill: ?int, average_age: ?float}>
     */
    private function classes(Collection $onRoll): Collection
    {
        $byArm = $onRoll->groupBy('class_arm_id');

        return ClassArm::query()
            ->with(['level', 'formTeacher'])
            ->whereIn('id', $byArm->keys())
            ->get()
            ->sortBy(fn (ClassArm $arm) => self::armSortKey($arm))
            ->values()
            ->map(function (ClassArm $arm) use ($byArm): array {
                /** @var Collection<int, Enrolment> $group */
                $group = $byArm->get($arm->id, collect());
                $ages = $group->map(fn (Enrolment $enrolment) => $enrolment->student->age())->filter(fn (?int $age) => $age !== null);

                return [
                    'arm' => $arm,
                    'section' => $arm->level->section,
                    'form_teacher' => $arm->formTeacher?->fullName(),
                    'capacity' => $arm->capacity,
                    'boys' => $this->countGender($group, Gender::Male),
                    'girls' => $this->countGender($group, Gender::Female),
                    'total' => $group->count(),
                    'new' => $group->reject(fn (Enrolment $enrolment) => isset($this->returning()[$enrolment->student_id]))->count(),
                    'fill' => $arm->capacity ? (int) round($group->count() / $arm->capacity * 100) : null,
                    'average_age' => $ages->isEmpty() ? null : round((float) $ages->avg(), 1),
                ];
            });
    }

    /**
     * @param  Collection<int, array{arm: ClassArm, section: ClassSection, form_teacher: ?string, capacity: ?int, boys: int, girls: int, total: int, new: int, fill: ?int, average_age: ?float}>  $classes
     * @return list<array{section: string, arms: int, boys: int, girls: int, total: int, share: int}>
     */
    private function sections(Collection $classes, int $onRoll): array
    {
        return $classes->groupBy(fn (array $row) => $row['section']->value)
            ->map(fn (Collection $rows) => [
                'section' => $rows->first()['section']->label(),
                'arms' => $rows->count(),
                'boys' => (int) $rows->sum('boys'),
                'girls' => (int) $rows->sum('girls'),
                'total' => (int) $rows->sum('total'),
                'share' => $onRoll > 0 ? (int) round($rows->sum('total') / $onRoll * 100) : 0,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Enrolment>  $onRoll
     * @return array<string, int> band => students
     */
    private function ages(Collection $onRoll): array
    {
        $bands = ['5 and under' => 0, '6–8' => 0, '9–11' => 0, '12–14' => 0, '15–17' => 0, '18 and over' => 0, 'Not recorded' => 0];

        foreach ($onRoll as $enrolment) {
            $age = $enrolment->student->age();
            $band = match (true) {
                $age === null => 'Not recorded',
                $age <= 5 => '5 and under',
                $age <= 8 => '6–8',
                $age <= 11 => '9–11',
                $age <= 14 => '12–14',
                $age <= 17 => '15–17',
                default => '18 and over',
            };
            $bands[$band]++;
        }

        return array_filter($bands, fn (int $count, string $band) => $band !== 'Not recorded' || $count > 0, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Counts by a student field, the largest few named and the rest folded into "Other".
     *
     * @param  Collection<int, Enrolment>  $onRoll
     * @param  callable(Enrolment): ?string  $field
     * @return array<string, int>
     */
    private function breakdown(Collection $onRoll, callable $field, int $top): array
    {
        $counts = $onRoll->countBy(fn (Enrolment $enrolment) => filled($value = $field($enrolment)) ? trim((string) $value) : 'Not recorded')->sortDesc();
        $unknown = $counts->pull('Not recorded', 0);
        $named = $counts->take($top);
        $other = $counts->skip($top)->sum();

        return $named
            ->when($other > 0, fn (Collection $rows) => $rows->put('Other', $other))
            ->when($unknown > 0, fn (Collection $rows) => $rows->put('Not recorded', $unknown))
            ->all();
    }

    /**
     * Students on roll in each session, oldest first.
     *
     * @return list<array{session: string, students: int, current: bool}>
     */
    private function trend(): array
    {
        $counts = Enrolment::query()
            ->where('status', '!=', EnrolmentStatus::Withdrawn)
            ->selectRaw('academic_session_id, count(*) as total')
            ->groupBy('academic_session_id')
            ->pluck('total', 'academic_session_id');

        return AcademicSession::query()->orderBy('starts_on')->get()
            ->map(fn (AcademicSession $session) => [
                'session' => $session->name,
                'students' => (int) ($counts[$session->id] ?? 0),
                'current' => $session->is($this->session),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Enrolment>  $enrolments
     */
    private function countGender(Collection $enrolments, Gender $gender): int
    {
        return $enrolments->filter(fn (Enrolment $enrolment) => $enrolment->student->gender === $gender)->count();
    }
}
