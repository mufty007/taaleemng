<?php

namespace App\Support\Dashboard;

use App\Enums\ClassworkStatus;
use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Quiz;
use App\Models\Score;
use App\Models\Staff;
use App\Models\Term;
use App\Support\Attendance\ClassRegister;
use Illuminate\Support\Collection;

/**
 * A teacher's day: their form class register, work waiting to be marked,
 * scores still to enter and what is due, most urgent first.
 */
final class TeacherDay
{
    /** @var Collection<int, ClassSubject>|null */
    private ?Collection $classSubjects = null;

    public function __construct(
        public readonly Staff $staff,
        public readonly ?AcademicSession $session,
        public readonly ?Term $term,
    ) {}

    /**
     * @return Collection<int, ClassSubject>
     */
    public function classSubjects(): Collection
    {
        return $this->classSubjects ??= ClassSubject::query()
            ->with(['subject', 'classArm.level'])
            ->where('teacher_id', $this->staff->id)
            ->get()
            ->sortBy(fn (ClassSubject $classSubject) => sprintf('%03d-%s-%s', $classSubject->classArm->level->position, $classSubject->classArm->name, $classSubject->subject->name))
            ->values();
    }

    /**
     * Today's register for each class this teacher is form teacher of.
     *
     * @return Collection<int, ClassRegister>
     */
    public function registers(): Collection
    {
        return ClassArm::query()->with(['level', 'formTeacher'])->where('form_teacher_id', $this->staff->id)->get()
            ->map(fn (ClassArm $arm) => ClassRegister::for($arm, ClassRegister::schoolDay(null)));
    }

    /**
     * Assignments with hand-ins waiting for a mark, most waiting first.
     *
     * @return Collection<int, Assignment>
     */
    public function toMark(): Collection
    {
        return Assignment::query()
            ->with('classSubject.subject', 'classSubject.classArm.level')
            ->whereIn('class_subject_id', $this->classSubjects()->pluck('id'))
            ->withCount(['submissions as unmarked_count' => fn ($query) => $query->whereNull('marked_at')])
            ->get()
            ->filter(fn (Assignment $assignment) => $assignment->unmarked_count > 0)
            ->sortByDesc('unmarked_count')
            ->values();
    }

    public function firstUnmarked(Assignment $assignment): ?AssignmentSubmission
    {
        return $assignment->submissions()->with('student')->whereNull('marked_at')->get()
            ->sortBy(fn (AssignmentSubmission $submission) => $submission->student->last_name.' '.$submission->student->first_name)
            ->first();
    }

    /**
     * Score entry for each class subject this term: students with any score out of the class.
     *
     * @return Collection<int, array{classSubject: ClassSubject, scored: int, students: int, rate: int}>
     */
    public function scoreProgress(): Collection
    {
        $ids = $this->classSubjects()->pluck('id');
        $scored = Score::query()->where('term_id', $this->term->id ?? 0)->whereIn('class_subject_id', $ids)
            ->selectRaw('class_subject_id, count(distinct enrolment_id) as students')->groupBy('class_subject_id')
            ->pluck('students', 'class_subject_id');
        $sizes = Enrolment::query()->where('academic_session_id', $this->session->id ?? 0)
            ->whereIn('class_arm_id', $this->classSubjects()->pluck('class_arm_id')->unique())
            ->selectRaw('class_arm_id, count(*) as students')->groupBy('class_arm_id')
            ->pluck('students', 'class_arm_id');

        return $this->classSubjects()
            ->filter(fn (ClassSubject $classSubject) => ($sizes[$classSubject->class_arm_id] ?? 0) > 0)
            ->map(function (ClassSubject $classSubject) use ($scored, $sizes): array {
                $students = (int) ($sizes[$classSubject->class_arm_id] ?? 0);
                $done = (int) ($scored[$classSubject->id] ?? 0);

                return ['classSubject' => $classSubject, 'scored' => $done, 'students' => $students, 'rate' => $students ? (int) round(min($done, $students) / $students * 100) : 0];
            })
            ->values();
    }

    /**
     * Published assignments due in the coming week, and quizzes open now.
     *
     * @return array{assignments: Collection<int, Assignment>, quizzes: Collection<int, Quiz>}
     */
    public function comingUp(): array
    {
        $ids = $this->classSubjects()->pluck('id');

        return [
            'assignments' => Assignment::query()->with('classSubject.subject', 'classSubject.classArm.level')->withCount('submissions')
                ->whereIn('class_subject_id', $ids)->where('status', ClassworkStatus::Published)
                ->whereBetween('due_at', [now(), now()->addWeek()])->orderBy('due_at')->get(),
            'quizzes' => Quiz::query()->with('classSubject.subject', 'classSubject.classArm.level')->withCount('attempts')
                ->whereIn('class_subject_id', $ids)->where('status', ClassworkStatus::Published)->latest()->get()
                ->filter(fn (Quiz $quiz) => $quiz->isOpen())->values(),
        ];
    }
}
