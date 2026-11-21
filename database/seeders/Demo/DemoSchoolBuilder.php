<?php

namespace Database\Seeders\Demo;

use App\Actions\Academic\AddClassArm;
use App\Actions\Academic\ApplyNigerianDefaults;
use App\Actions\Academic\StartSession;
use App\Actions\Admissions\SubmitApplication;
use App\Actions\People\AddStaffMember;
use App\Actions\People\EnrolStudent;
use App\Enums\ApplicantStatus;
use App\Enums\ClassSection;
use App\Enums\CommentAudience;
use App\Enums\EnrolmentStatus;
use App\Enums\SchoolRole;
use App\Enums\TermStatus;
use App\Models\AcademicSession;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\ReportTrait;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Term;
use App\Support\Results\CommentBank;
use App\Support\Results\PromotionRules;
use App\Support\Results\ResultCalculator;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds one fully populated demo school: classes, two sessions, staff with
 * teaching assignments, and students with families and class history.
 * Every login it creates uses the password "password".
 */
final class DemoSchoolBuilder
{
    public const PASSWORD = 'password';

    /** @var array<string, array{guardian: array<string, string|null>, heritage: string, surname: string, religion: string, state: string, address: string, kids: int}> */
    private array $families = [];

    /** @var array<string, Staff> */
    private array $staffByKey = [];

    public function __construct(
        public readonly School $school,
        private NigerianPeople $people,
        private string $city,
        private array $heritageWeights,
        private string $emailDomain,
    ) {}

    /**
     * @param  callable(self): void  $build
     */
    public function build(callable $build): void
    {
        app(CurrentSchool::class)->run($this->school, fn () => $build($this));
    }

    /**
     * Standard classes and subjects, plus extra arms, e.g. ['Primary 1' => ['B'], 'JSS 1' => ['B']].
     *
     * @param  array<string, list<string>>  $extraArms
     */
    public function classes(array $extraArms = []): void
    {
        app(ApplyNigerianDefaults::class)->handle();

        foreach ($extraArms as $levelName => $armNames) {
            $level = ClassLevel::query()->where('name', $levelName)->firstOrFail();

            foreach ($armNames as $armName) {
                app(AddClassArm::class)->handle($level, $armName);
            }
        }
    }

    /**
     * Last session (closed) and this session, with its First Term current.
     */
    public function sessions(int $previousYear, int $currentYear): void
    {
        $previous = app(StartSession::class)->handle($previousYear);
        $previous->terms()->update(['status' => TermStatus::Closed]);

        app(StartSession::class)->handle($currentYear, makeCurrent: true);
    }

    /**
     * @param  array{title: string, first: string, last: string, gender: string, designation: string, department?: string, email?: string, phone?: string, qualification?: string, joined?: string}  $person
     */
    public function staff(string $key, array $person, SchoolRole $role): Staff
    {
        $email = $person['email'] ?? Str::lower(Str::ascii($person['first'].'.'.$person['last'])).'@'.$this->emailDomain;
        $phone = $person['phone'] ?? $this->people->phone();
        $this->people->reservePhone($phone);

        ['staff' => $staff] = app(AddStaffMember::class)->handle([
            'title' => $person['title'],
            'first_name' => $person['first'],
            'last_name' => $person['last'],
            'gender' => $person['gender'],
            'email' => $email,
            'phone' => $phone,
            'designation' => $person['designation'],
            'department' => $person['department'] ?? null,
            'employment_type' => 'full_time',
            'date_employed' => $person['joined'] ?? Carbon::create($this->people->between(2012, 2025), $this->people->between(1, 9), 1)->toDateString(),
            'qualification' => $person['qualification'] ?? $this->people->pick(['B.Ed', 'B.Sc (Ed)', 'NCE', 'M.Ed', 'B.A (Ed)', 'PGDE']),
        ], $role, self::PASSWORD);

        return $this->staffByKey[$key] = $staff;
    }

    public function staffMember(string $key): Staff
    {
        return $this->staffByKey[$key];
    }

    public function arm(string $levelName, string $armName = 'A'): ClassArm
    {
        return ClassArm::query()
            ->whereHas('level', fn ($query) => $query->where('name', $levelName))
            ->where('name', $armName)
            ->firstOrFail();
    }

    /**
     * One class teacher per nursery/primary arm: form teacher, and teacher of
     * every subject in that arm except the ones a specialist covers.
     *
     * @return array<int, Staff> class arm id => class teacher
     */
    public function classTeachers(): array
    {
        $teachers = [];

        $arms = ClassArm::query()
            ->with('level')
            ->whereHas('level', fn ($query) => $query->whereIn('section', [ClassSection::Nursery, ClassSection::Primary]))
            ->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d%s', $arm->level->position, $arm->name));

        foreach ($arms as $arm) {
            $gender = $this->people->chance(78) ? 'female' : 'male';
            $heritage = $this->people->heritage($this->heritageWeights);
            $first = $this->people->firstName($heritage, $gender);
            $last = $this->people->surname($heritage);

            $teacher = $this->staff('class-'.$arm->id, [
                'title' => $gender === 'female' ? $this->people->pick(['Mrs.', 'Mrs.', 'Miss']) : 'Mr.',
                'first' => $first,
                'last' => $last,
                'gender' => $gender,
                'email' => Str::lower(Str::ascii($first.'.'.$last)).'.'.Str::lower(Str::replace(' ', '', $arm->displayName())).'@'.$this->emailDomain,
                'designation' => 'Class Teacher, '.$arm->level->name.$arm->name,
                'department' => 'Nursery & Primary',
                'qualification' => $this->people->pick(['NCE', 'B.Ed (Early Childhood)', 'B.Ed (Primary Education)', 'NCE']),
            ], SchoolRole::Teacher);

            $arm->update(['form_teacher_id' => $teacher->id]);
            $teachers[$arm->id] = $teacher;
        }

        return $teachers;
    }

    /**
     * Give every class subject a teacher: the specialist for that subject and
     * section if there is one, otherwise the arm's class teacher.
     *
     * @param  array<string, array<string, Staff>>  $specialists  subject code => [section value => staff]
     * @param  array<int, Staff>  $classTeachers
     */
    public function assignTeachers(array $specialists, array $classTeachers): void
    {
        ClassSubject::query()->with(['subject', 'classArm.level'])->get()->each(function (ClassSubject $classSubject) use ($specialists, $classTeachers): void {
            $section = $classSubject->classArm->level->section->value;
            $teacher = $specialists[$classSubject->subject->code][$section] ?? $classTeachers[$classSubject->class_arm_id] ?? null;

            if ($teacher) {
                $classSubject->update(['teacher_id' => $teacher->id]);
            }
        });
    }

    /**
     * @param  array<string, string>  $formClasses  "JSS 2|A" => staff key
     */
    public function formTeachers(array $formClasses): void
    {
        foreach ($formClasses as $arm => $staffKey) {
            [$level, $name] = explode('|', $arm);
            $this->arm($level, $name)->update(['form_teacher_id' => $this->staffMember($staffKey)->id]);
        }
    }

    /**
     * Fill every arm with students. Named students (and their families) are added on top.
     *
     * @param  array<string, int>  $perArmBySection  section value => students per arm
     * @param  list<array<string, mixed>>  $namedStudents
     */
    public function students(array $perArmBySection, array $namedStudents = []): void
    {
        $session = AcademicSession::current();
        $levels = ClassLevel::query()->orderBy('position')->get()->values();
        $specs = [];

        foreach ($levels as $index => $level) {
            $arms = $level->arms()->with('stream')->get();

            foreach ($arms as $arm) {
                $count = $perArmBySection[$level->section->value] ?? 0;

                for ($i = 0; $i < $count; $i++) {
                    $specs[] = $this->randomStudent($level, $index, $arm);
                }
            }
        }

        foreach ($namedStudents as $named) {
            $level = $levels->firstWhere('name', $named['level']);
            $index = $levels->search(fn (ClassLevel $candidate) => $candidate->is($level));
            $spec = $this->randomStudent($level, $index, $this->arm($named['level'], $named['arm'] ?? 'A'), $this->familyFor($named['family']));
            $spec['student'] = array_replace($spec['student'], $named['student']);

            if (str_starts_with($spec['student']['admitted_on'], (string) $session->starts_on->year)) {
                $spec['history_arm'] = null;
            }

            $specs[] = $spec;
        }

        // Admission numbers run in admission order, so older students have older numbers.
        usort($specs, fn (array $a, array $b) => strcmp($a['student']['admitted_on'].$a['student']['last_name'], $b['student']['admitted_on'].$b['student']['last_name']));

        $previousSession = AcademicSession::query()->where('id', '!=', $session->id)->orderByDesc('starts_on')->first();

        foreach ($specs as $spec) {
            $result = app(EnrolStudent::class)->handle($spec['student'], $spec['arm'], $spec['guardian'], self::PASSWORD);

            if ($spec['second_guardian'] && ! $result->student->guardians()->where('phone', $spec['second_guardian']['phone'])->exists()) {
                $second = Guardian::query()->firstOrCreate(['phone' => $spec['second_guardian']['phone']], $spec['second_guardian']);
                $result->student->guardians()->syncWithoutDetaching([$second->id => ['is_primary' => false]]);
            }

            // Students who were here last session get last session's class too.
            if ($previousSession && $spec['history_arm']) {
                $result->student->enrolments()->create([
                    'academic_session_id' => $previousSession->id,
                    'class_arm_id' => $spec['history_arm']->id,
                    'status' => EnrolmentStatus::Promoted,
                ]);
            }
        }
    }

    /**
     * Realistic scores: every term of last session (published to families), and
     * CA1 + CA2 of the current term with the exam still to come. Each student has a
     * steady ability so their grades are consistent across subjects and terms.
     */
    public function scores(): void
    {
        mt_srand(2026 + $this->school->id);

        $components = AssessmentComponent::query()->orderBy('position')->get();
        $current = Term::current();
        $sessions = AcademicSession::query()->with('terms')->get()->keyBy('id');
        $armSubjects = ClassSubject::query()->with('subject')->get()->groupBy('class_arm_id');
        $now = now();
        $rows = [];

        $flush = function () use (&$rows): void {
            if ($rows !== []) {
                DB::table('scores')->insert($rows);
                $rows = [];
            }
        };

        Enrolment::query()->with('student')->orderBy('id')->each(function (Enrolment $enrolment) use ($components, $current, $sessions, $armSubjects, $now, &$rows, $flush): void {
            $ability = 40 + ($enrolment->student_id * 7919) % 45;

            foreach ($sessions[$enrolment->academic_session_id]->terms as $term) {
                if ($term->starts_on->isFuture()) {
                    continue;
                }

                $termComponents = $term->is($current) ? $components->take(2) : $components;

                foreach ($armSubjects[$enrolment->class_arm_id] ?? [] as $classSubject) {
                    $code = $classSubject->subject->code;

                    if (($code === 'CRS' && $enrolment->student->religion === 'Islam') || ($code === 'IRS' && $enrolment->student->religion !== 'Islam')) {
                        continue;
                    }

                    $subjectAbility = $ability + (crc32($code) % 15) - 7 + $term->sequence;

                    foreach ($termComponents as $component) {
                        $share = max(0.15, min(0.98, ($subjectAbility + mt_rand(-12, 12)) / 100));
                        $rows[] = [
                            'school_id' => $this->school->id,
                            'term_id' => $term->id,
                            'enrolment_id' => $enrolment->id,
                            'class_subject_id' => $classSubject->id,
                            'assessment_component_id' => $component->id,
                            'score' => round($component->max_score * $share),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if (count($rows) >= 1000) {
                            $flush();
                        }
                    }
                }
            }
        });
        $flush();

        // Last session's results are out: publish every class for every term.
        $previous = $sessions->first(fn (AcademicSession $session) => ! $session->is_current);

        foreach ($previous?->terms ?? [] as $term) {
            foreach (Enrolment::query()->where('academic_session_id', $previous->id)->distinct()->pluck('class_arm_id') as $armId) {
                ResultPublication::query()->create(['term_id' => $term->id, 'class_arm_id' => $armId, 'published_at' => $term->ends_on->copy()->addDays(3)]);
            }
        }
    }

    /**
     * Report-card remarks for last session (every term) and for the form teacher
     * classes of this term: attendance, trait ratings, comments and, for the
     * third term, promotion decisions. Run after scores().
     */
    public function reportRemarks(): void
    {
        mt_srand(4040 + $this->school->id);

        $calculator = app(ResultCalculator::class);
        $comments = app(CommentBank::class);
        $traitIds = ReportTrait::query()->pluck('id')->all();
        $passMark = PromotionRules::passMark();
        $current = Term::current();
        $now = now();
        $rows = [];

        foreach (Term::query()->with('academicSession')->get() as $term) {
            if ($term->starts_on->isFuture()) {
                continue;
            }

            // Weekdays in term less a mid-term week.
            $term->update(['school_days' => (int) $term->starts_on->diffInWeekdays($term->ends_on) - 5]);
        }

        $terms = Term::query()->with('academicSession')->where(fn ($query) => $query
            ->whereHas('academicSession', fn ($session) => $session->where('is_current', false))
            ->orWhere('id', $current->id))
            ->get();

        foreach ($terms as $term) {
            $isFinal = $term->isFinal();
            $arms = ClassArm::query()->with('level')
                ->whereIn('id', Enrolment::query()->where('academic_session_id', $term->academic_session_id)->distinct()->pluck('class_arm_id'))
                ->when($term->is($current), fn ($query) => $query->whereNotNull('form_teacher_id')->whereHas('level', fn ($level) => $level->where('name', 'JSS 2')))
                ->get();

            foreach ($arms as $arm) {
                $results = $calculator->forClass($arm, $term);
                $session = $isFinal ? $calculator->sessionSoFar($arm, $term, $results) : [];
                $finalClass = $arm->level->isFinal();

                foreach ($results->students as $index => $row) {
                    // This term the exams are not in yet: the form teacher has only done attendance and ratings for part of the class.
                    if ($row['average'] === null || ($term->is($current) && $index >= 12)) {
                        continue;
                    }

                    $enrolmentId = $row['enrolment']->id;
                    $firstName = $row['student']->first_name;
                    $lean = $row['average'] >= 60 ? 1 : 0;

                    $rows[] = [
                        'school_id' => $this->school->id,
                        'term_id' => $term->id,
                        'enrolment_id' => $enrolmentId,
                        'days_present' => max(0, $term->school_days - mt_rand(0, $lean ? 3 : 8)),
                        'traits' => json_encode(collect($traitIds)->mapWithKeys(fn (int $id) => [$id => min(5, mt_rand(2, 4) + $lean)])->all()),
                        'teacher_comment' => $term->is($current) ? null : $comments->suggest(CommentAudience::FormTeacher, $row['average'], $firstName, $enrolmentId),
                        'principal_comment' => $term->is($current) ? null : $comments->suggest(CommentAudience::Principal, $row['average'], $firstName, $enrolmentId),
                        'promotion_decision' => $isFinal ? PromotionRules::suggest($session[$enrolmentId]['average'] ?? null, $finalClass, $passMark)?->value : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($rows) >= 500) {
                    DB::table('term_reports')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('term_reports')->insert($rows);
        }
    }

    /**
     * Applications at every stage of admissions, mostly for the entry classes.
     *
     * @param  array<string, int>  $statusCounts  status value => how many
     * @param  list<string>  $entryLevels
     */
    public function applicants(array $statusCounts, array $entryLevels): void
    {
        $submit = app(SubmitApplication::class);
        $levels = ClassLevel::query()->whereIn('name', $entryLevels)->get();
        $examDay = now()->next(Carbon::SATURDAY)->setTime(9, 0);

        foreach ($statusCounts as $statusValue => $count) {
            $status = ApplicantStatus::from($statusValue);

            for ($i = 0; $i < $count; $i++) {
                $heritage = $this->people->heritage($this->heritageWeights);
                $religion = $this->people->religion($heritage);
                $surname = $this->people->surname($heritage);
                $gender = $this->people->chance(50) ? 'female' : 'male';
                $level = $this->people->pick($levels->all());
                $relationship = $this->people->pick(['father', 'mother', 'mother']);
                $parentGender = $relationship === 'mother' ? 'female' : 'male';
                $occupation = $this->people->occupation();
                $age = match ($level->section) {
                    ClassSection::Nursery => 3,
                    ClassSection::Primary => 6,
                    ClassSection::Junior => 11,
                    default => 14,
                };

                $applicant = $submit->handle([
                    'class_level_id' => $level->id,
                    'first_name' => $this->people->firstName($heritage, $gender),
                    'last_name' => $surname,
                    'gender' => $gender,
                    'date_of_birth' => now()->subYears($age)->subDays($this->people->between(0, 300))->toDateString(),
                    'religion' => $religion,
                    'state_of_origin' => $this->people->state($heritage),
                    'previous_school' => $age > 4 ? $this->people->pick(['Bright Future Academy', 'Kiddies Haven School', 'Unity Primary School', 'Model College', 'St. Mary’s School']) : null,
                    'address' => $this->people->address($this->city),
                    'guardian_title' => $this->people->parentTitle($parentGender, $religion, $heritage, $occupation),
                    'guardian_first_name' => $this->people->firstName($heritage, $parentGender),
                    'guardian_last_name' => $surname,
                    'guardian_relationship' => $relationship,
                    'guardian_phone' => $this->people->phone(),
                    'guardian_occupation' => $occupation,
                ], $this->people->chance(70) ? 'online' : 'office');

                $hasExam = in_array($status, [ApplicantStatus::ExamScheduled, ApplicantStatus::Offered, ApplicantStatus::Accepted, ApplicantStatus::Rejected], true);
                $examDone = $status !== ApplicantStatus::ExamScheduled;

                $applicant->forceFill([
                    'status' => $status,
                    'exam_at' => $hasExam ? ($examDone ? $examDay->copy()->subWeeks(2) : $examDay) : null,
                    'exam_score' => $hasExam && $examDone ? ($status === ApplicantStatus::Rejected ? $this->people->between(18, 38) : $this->people->between(52, 91)) : null,
                    'created_at' => now()->subDays($this->people->between($examDone && $hasExam ? 18 : 1, $examDone && $hasExam ? 40 : 16))->setTime($this->people->between(8, 20), $this->people->between(0, 59)),
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $family
     * @return array{student: array<string, mixed>, arm: ClassArm, guardian: array<string, string|null>, second_guardian: array<string, string|null>|null, history_arm: ?ClassArm}
     */
    private function randomStudent(ClassLevel $level, int $levelIndex, ClassArm $arm, ?array $family = null): array
    {
        $family ??= $this->family();
        $gender = $this->people->chance(50) ? 'female' : 'male';
        $heritage = $family['heritage'];

        $ageInSeptember = match ($level->section) {
            ClassSection::Nursery => 2 + $level->position,
            ClassSection::Primary => 5 + ($level->position - 10),
            ClassSection::Junior => 11 + ($level->position - 20),
            ClassSection::Senior => 14 + ($level->position - 30),
            ClassSection::Islamiyya => 6 + ($level->position - 40),
        };
        $currentYear = (int) AcademicSession::current()->starts_on->format('Y');
        $dateOfBirth = Carbon::create($currentYear - $ageInSeptember, 9, 1)->subDays($this->people->between(0, 360));

        $yearsHere = $this->people->between(0, min($levelIndex, 5));
        $admittedOn = Carbon::create($currentYear - $yearsHere, 9, 1)->addDays($this->people->between(7, 21));

        $genotype = $this->people->genotype();
        $medical = match (true) {
            $genotype === 'SS' => 'Sickle cell (SS). Needs plenty of water and rest; call a parent at the first sign of a crisis.',
            $this->people->chance(4) => 'Allergic to groundnuts. Carries an antihistamine.',
            $this->people->chance(3) => 'Asthmatic. Inhaler kept with the school nurse.',
            default => null,
        };

        $historyArm = null;

        if ($yearsHere > 0 && $levelIndex > 0) {
            $previousLevel = ClassLevel::query()->orderBy('position')->get()->values()->get($levelIndex - 1);
            $historyArm = $previousLevel?->arms()->where('name', $arm->name)->first() ?? $previousLevel?->arms()->first();
        }

        $middleName = $this->people->chance(70) ? $this->people->firstName($heritage, $gender) : null;
        $firstName = $this->people->firstName($heritage, $gender);

        return [
            'student' => [
                'first_name' => $firstName,
                'middle_name' => $middleName !== $firstName ? $middleName : null,
                'last_name' => $family['surname'],
                'gender' => $gender,
                'date_of_birth' => $dateOfBirth->toDateString(),
                'state_of_origin' => $family['state'],
                'religion' => $family['religion'],
                'blood_group' => $this->people->bloodGroup(),
                'genotype' => $genotype,
                'address' => $family['address'],
                'previous_school' => $yearsHere === 0 && $levelIndex > 0 ? $this->people->pick(['Kiddies Haven School', 'Bright Stars Academy', 'Government Primary School', 'Excel International School', 'Command Children School', 'Unity Model College']) : null,
                'medical_notes' => $medical,
                'admitted_on' => $admittedOn->toDateString(),
            ],
            'arm' => $arm,
            'guardian' => $family['guardian'],
            'second_guardian' => $family['second_guardian'] ?? null,
            'history_arm' => $historyArm,
        ];
    }

    /**
     * Either an existing family (a sibling) or a new one.
     *
     * @return array<string, mixed>
     */
    private function family(): array
    {
        $open = array_filter($this->families, fn (array $family) => $family['kids'] < 3);

        if ($open !== [] && $this->people->chance(22)) {
            $key = $this->people->pick(array_keys($open));
            $this->families[$key]['kids']++;

            return $this->families[$key];
        }

        $heritage = $this->people->heritage($this->heritageWeights);
        $surname = $this->people->surname($heritage);
        $religion = $this->people->religion($heritage);
        $relationship = $this->people->pick(['father', 'father', 'father', 'mother', 'mother', 'uncle', 'aunt']);
        $guardianGender = in_array($relationship, ['mother', 'aunt'], true) ? 'female' : 'male';
        $first = $this->people->firstName($heritage, $guardianGender);
        $occupation = $this->people->occupation();

        $guardian = [
            'title' => $this->people->parentTitle($guardianGender, $religion, $heritage, $occupation),
            'first_name' => $first,
            'last_name' => $surname,
            'relationship' => $relationship,
            'phone' => $this->people->phone(),
            'email' => $this->people->chance(65) ? Str::lower(Str::ascii($first.'.'.$surname)).$this->people->between(1, 99).'@family.test' : null,
            'occupation' => $occupation,
        ];

        $secondGuardian = null;

        if (in_array($relationship, ['father', 'mother'], true) && $this->people->chance(35)) {
            $spouseRelationship = $relationship === 'father' ? 'mother' : 'father';
            $spouseGender = $spouseRelationship === 'mother' ? 'female' : 'male';
            $spouseOccupation = $this->people->occupation();
            $secondGuardian = [
                'title' => $this->people->parentTitle($spouseGender, $religion, $heritage, $spouseOccupation),
                'first_name' => $this->people->firstName($heritage, $spouseGender),
                'last_name' => $surname,
                'relationship' => $spouseRelationship,
                'phone' => $this->people->phone(),
                'email' => null,
                'occupation' => $spouseOccupation,
            ];
        }

        $key = $guardian['phone'];

        return $this->families[$key] = [
            'guardian' => $guardian,
            'second_guardian' => $secondGuardian,
            'heritage' => $heritage,
            'surname' => $surname,
            'religion' => $religion,
            'state' => $this->people->state($heritage),
            'address' => $this->people->address($this->city),
            'kids' => 1,
        ];
    }

    /**
     * Register a named family up front so named students can share it.
     *
     * @param  array<string, string|null>  $guardian
     * @param  array<string, string|null>|null  $secondGuardian
     */
    public function namedFamily(array $guardian, ?array $secondGuardian, string $heritage, string $religion, string $state): void
    {
        $this->people->reservePhone($guardian['phone']);

        if ($secondGuardian) {
            $this->people->reservePhone($secondGuardian['phone']);
        }

        $this->families[$guardian['phone']] = [
            'guardian' => $guardian,
            'second_guardian' => $secondGuardian,
            'heritage' => $heritage,
            'surname' => $guardian['last_name'],
            'religion' => $religion,
            'state' => $state,
            'address' => $this->people->address($this->city),
            'kids' => 3, // closed to random siblings
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function familyFor(string $phone): array
    {
        return $this->families[$phone];
    }

    /**
     * @return Collection<int, Stream>
     */
    public function streams(): Collection
    {
        return Stream::query()->get();
    }

    public function currentTerm(): Term
    {
        return Term::current();
    }
}
