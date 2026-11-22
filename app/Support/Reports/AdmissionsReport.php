<?php

namespace App\Support\Reports;

use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\ClassLevel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Applications for a session from form to first day: the funnel, demand by class and month,
 * the entrance exam score spread, and where applications came from.
 */
final class AdmissionsReport implements SchoolReport
{
    /** @var EloquentCollection<int, Applicant>|null */
    private ?EloquentCollection $applicants = null;

    public function __construct(public readonly AcademicSession $session) {}

    public function periodLabel(): string
    {
        return 'For the '.$this->session->name.' session';
    }

    public function figures(): array
    {
        $applicants = $this->applicants();
        $offered = $applicants->filter(fn (Applicant $applicant) => in_array($applicant->status, self::offeredStatuses(), true));
        $enrolled = $applicants->where('status', ApplicantStatus::Enrolled);
        $examined = $applicants->filter(fn (Applicant $applicant) => $applicant->exam_score !== null);

        return [
            'session' => $this->session,
            'stats' => [
                'applications' => $applicants->count(),
                'in_progress' => $applicants->filter(fn (Applicant $applicant) => in_array($applicant->status->stage(), ['new', 'exam'], true))->count(),
                'examined' => $examined->count(),
                'offered' => $offered->count(),
                'enrolled' => $enrolled->count(),
                'rejected' => $applicants->where('status', ApplicantStatus::Rejected)->count(),
                'conversion' => $applicants->isEmpty() ? null : (int) round($enrolled->count() / $applicants->count() * 100),
                'acceptance' => $offered->isEmpty() ? null : (int) round($enrolled->count() / $offered->count() * 100),
                'average_score' => $examined->isEmpty() ? null : round((float) $examined->avg('exam_score'), 1),
            ],
            'funnel' => [
                ['label' => 'Applied', 'count' => $applicants->count()],
                ['label' => 'Sat the entrance exam', 'count' => $examined->count()],
                ['label' => 'Offered a place', 'count' => $offered->count()],
                ['label' => 'Enrolled', 'count' => $enrolled->count()],
            ],
            'statuses' => collect(ApplicantStatus::cases())
                ->map(fn (ApplicantStatus $status) => ['status' => $status, 'count' => $applicants->where('status', $status)->count()])
                ->filter(fn (array $row) => $row['count'] > 0)
                ->values()
                ->all(),
            'levels' => $this->levels(),
            'months' => $applicants->sortBy('created_at')
                ->groupBy(fn (Applicant $applicant) => $applicant->created_at->format('M Y'))
                ->map(fn (Collection $group) => $group->count())
                ->all(),
            'scores' => $this->scoreBands($examined),
            'sources' => $applicants->countBy(fn (Applicant $applicant) => $applicant->source === 'online' ? 'Online form' : 'At the school office')->sortDesc()->all(),
            'genders' => [
                'Boys' => $applicants->where('gender', Gender::Male)->count(),
                'Girls' => $applicants->where('gender', Gender::Female)->count(),
            ],
        ];
    }

    public function csv(): StreamedResponse
    {
        $rows = $this->applicants()->sortBy('created_at')->map(fn (Applicant $applicant) => [
            $applicant->application_no,
            $applicant->created_at->format('Y-m-d'),
            $applicant->registerName(),
            $applicant->gender->label(),
            $applicant->date_of_birth?->format('Y-m-d'),
            $applicant->classLevel->name,
            $applicant->status->label(),
            $applicant->source === 'online' ? 'Online form' : 'School office',
            $applicant->exam_score,
            $applicant->previous_school,
            $applicant->guardianName(),
            $applicant->guardian_phone,
        ]);

        return CsvDownload::make(
            'admissions-'.str_replace('/', '-', $this->session->name).'.csv',
            ['Application no.', 'Applied on', 'Applicant', 'Gender', 'Date of birth', 'Class applied for', 'Status', 'Applied through', 'Exam score', 'Previous school', 'Parent / guardian', 'Phone'],
            $rows,
        );
    }

    /**
     * Statuses that mean the school offered the child a place.
     *
     * @return list<ApplicantStatus>
     */
    private static function offeredStatuses(): array
    {
        return [ApplicantStatus::Offered, ApplicantStatus::Accepted, ApplicantStatus::Enrolled];
    }

    /**
     * @return EloquentCollection<int, Applicant>
     */
    private function applicants(): EloquentCollection
    {
        return $this->applicants ??= Applicant::query()
            ->where('academic_session_id', $this->session->id)
            ->with('classLevel')
            ->get();
    }

    /**
     * @return Collection<int, array{level: ClassLevel, applied: int, offered: int, enrolled: int, rejected: int, average_score: ?float}>
     */
    private function levels(): Collection
    {
        $byLevel = $this->applicants()->groupBy(fn (Applicant $applicant): int => $applicant->class_level_id);

        return ClassLevel::query()
            ->whereIn('id', $byLevel->keys())
            ->orderBy('position')
            ->get()
            ->map(function (ClassLevel $level) use ($byLevel): array {
                $group = $byLevel->get($level->id, new EloquentCollection);
                $scores = $group->filter(fn (Applicant $applicant) => $applicant->exam_score !== null);

                return [
                    'level' => $level,
                    'applied' => $group->count(),
                    'offered' => $group->filter(fn (Applicant $applicant) => in_array($applicant->status, self::offeredStatuses(), true))->count(),
                    'enrolled' => $group->where('status', ApplicantStatus::Enrolled)->count(),
                    'rejected' => $group->where('status', ApplicantStatus::Rejected)->count(),
                    'average_score' => $scores->isEmpty() ? null : round((float) $scores->avg('exam_score'), 1),
                ];
            });
    }

    /**
     * @param  Collection<int, Applicant>  $examined
     * @return array<string, int>
     */
    private function scoreBands(Collection $examined): array
    {
        $bands = ['Below 40' => 0, '40–49' => 0, '50–59' => 0, '60–69' => 0, '70–79' => 0, '80 and above' => 0];

        foreach ($examined as $applicant) {
            $score = (float) $applicant->exam_score;
            $band = match (true) {
                $score < 40 => 'Below 40',
                $score < 50 => '40–49',
                $score < 60 => '50–59',
                $score < 70 => '60–69',
                $score < 80 => '70–79',
                default => '80 and above',
            };
            $bands[$band]++;
        }

        return $bands;
    }
}
