<?php

namespace Database\Seeders\Demo;

use App\Actions\Academic\AddClassArm;
use App\Actions\Academic\ApplyNigerianDefaults;
use App\Actions\Academic\GenerateExamTimetable;
use App\Actions\Academic\StartSession;
use App\Actions\Admissions\SubmitApplication;
use App\Actions\Fees\GenerateInvoices;
use App\Actions\Fees\RecordPayment;
use App\Actions\Fees\StartOnlinePayment;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Payroll\PreparePayroll;
use App\Actions\People\AddStaffMember;
use App\Actions\People\EnrolStudent;
use App\Actions\Timetable\AutofillTimetable;
use App\Enums\ApplicantStatus;
use App\Enums\ClassSection;
use App\Enums\CommentAudience;
use App\Enums\EnrolmentStatus;
use App\Enums\InventoryCategory;
use App\Enums\PaymentMethod;
use App\Enums\PayrollRunStatus;
use App\Enums\SchoolRole;
use App\Enums\StockMovementType;
use App\Enums\TermStatus;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\ExamPaper;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\PayrollRun;
use App\Models\Period;
use App\Models\ReportTrait;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Term;
use App\Support\Finance\CashflowPeriod;
use App\Support\Results\CommentBank;
use App\Support\Results\PromotionRules;
use App\Support\Results\ResultCalculator;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
     * @param  array{title: string, first: string, last: string, gender: string, designation: string, department?: string, email?: string, phone?: string, qualification?: string, joined?: string, basic_salary?: int, allowances?: int, deductions?: int}  $person
     */
    public function staff(string $key, array $person, SchoolRole $role): Staff
    {
        $email = $person['email'] ?? Str::lower(Str::ascii($person['first'].'.'.$person['last'])).'@'.$this->emailDomain;
        $phone = $person['phone'] ?? $this->people->phone();
        $this->people->reservePhone($phone);
        $pay = $this->salaryFor($role, $person);

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
            'basic_salary' => $pay['basic'],
            'allowances' => $pay['allowances'],
            'deductions' => $pay['deductions'],
            'bank_name' => $this->people->pick(['Access Bank', 'GTBank', 'Zenith Bank', 'UBA', 'First Bank', 'Stanbic IBTC']),
            'bank_account_name' => trim($person['title'].' '.$person['first'].' '.$person['last']),
            'bank_account_number' => (string) $this->people->between(1000000000, 1999999999),
        ], $role, self::PASSWORD);

        return $this->staffByKey[$key] = $staff;
    }

    /**
     * Monthly pay in kobo, by role. Overrides in $person win.
     *
     * @param  array{basic_salary?: int, allowances?: int, deductions?: int}  $person
     * @return array{basic: int, allowances: int, deductions: int}
     */
    private function salaryFor(SchoolRole $role, array $person): array
    {
        [$basic, $allowances] = match ($role) {
            SchoolRole::SchoolAdmin => [450_000_00, 50_000_00],
            SchoolRole::Principal => [380_000_00, 40_000_00],
            SchoolRole::HeadTeacher => [320_000_00, 35_000_00],
            SchoolRole::Bursar => [200_000_00, 25_000_00],
            SchoolRole::StoreKeeper => [120_000_00, 10_000_00],
            default => [$this->people->between(140, 190) * 1_000_00, $this->people->between(10, 30) * 1_000_00],
        };

        $basic = $person['basic_salary'] ?? $basic;
        $allowances = $person['allowances'] ?? $allowances;
        $deductions = $person['deductions'] ?? (int) round(($basic + $allowances) * 0.12);

        return compact('basic', 'allowances', 'deductions');
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
     * Fee items and a schedule for every term, invoices for last session and
     * this term, and payments: last session almost all paid, this term a mix of
     * paid, part paid and not yet paid, by transfer, cash, POS and online.
     *
     * @param  array<string, int>  $tuitionBySection  naira per term, by class section
     */
    public function fees(array $tuitionBySection): void
    {
        mt_srand(7070 + $this->school->id);

        $items = collect(FeeItem::NIGERIAN_DEFAULTS)->mapWithKeys(fn (string $name, int $index) => [$name => FeeItem::query()->create(['name' => $name, 'position' => $index + 1])]);
        $bus = FeeItem::query()->create(['name' => 'School bus', 'is_optional' => true, 'position' => 20]);
        $terms = Term::query()->with('academicSession')->orderBy('starts_on')->get();
        $current = Term::current();

        foreach ($terms as $term) {
            foreach (ClassLevel::query()->get() as $level) {
                $naira = [
                    'Tuition' => $tuitionBySection[$level->section->value] ?? 150_000,
                    'Development levy' => $term->sequence === 1 ? 25_000 : 0,
                    'PTA levy' => 5_000,
                    'Books & stationery' => $term->sequence === 1 ? 18_000 : 0,
                    'Exam fee' => in_array($level->name, ['JSS 3', 'SSS 3'], true) && $term->sequence === 2 ? 35_000 : 7_500,
                    'Sports & clubs' => 5_000,
                ];

                foreach ($naira as $name => $amount) {
                    if ($amount > 0) {
                        FeeStructure::query()->create(['term_id' => $term->id, 'class_level_id' => $level->id, 'fee_item_id' => $items[$name]->id, 'amount' => $amount * 100]);
                    }
                }

                FeeStructure::query()->create(['term_id' => $term->id, 'class_level_id' => $level->id, 'fee_item_id' => $bus->id, 'amount' => 40_000_00]);
            }
        }

        foreach ($terms->filter(fn (Term $term) => ! $term->starts_on->isFuture()) as $term) {
            app(GenerateInvoices::class)->handle($term);
            Invoice::query()->where('term_id', $term->id)->update([
                'issued_at' => $term->starts_on->copy()->subWeek(),
                'due_on' => $term->starts_on->copy()->addWeeks(2),
            ]);
        }

        $record = app(RecordPayment::class);
        $bursar = $this->staffMember('bursar')->user;

        foreach (Invoice::query()->with(['student', 'term'])->orderBy('issued_at')->orderBy('id')->get() as $invoice) {
            $roll = mt_rand(1, 100);
            $share = $invoice->term_id === $current->id
                ? match (true) {
                    $roll <= 55 => 1.0, $roll <= 80 => mt_rand(35, 85) / 100, default => 0.0
                }
            : ($roll <= 98 ? 1.0 : mt_rand(40, 80) / 100);
            $amount = $share === 1.0 ? $invoice->total : (int) (round($invoice->total * $share / 100_000) * 100_000);

            if ($amount <= 0) {
                continue;
            }

            $instalments = $share === 1.0 && mt_rand(1, 100) <= 30 ? [(int) (round($amount * 0.6 / 100_000) * 100_000)] : [];
            $instalments[] = $amount - array_sum($instalments);

            foreach ($instalments as $index => $instalment) {
                // Most families pay around resumption; part payments and second instalments trickle in through the term.
                $elapsed = max(1, (int) $invoice->term->starts_on->diffInDays(min(now(), $invoice->term->ends_on)));
                $paidAt = ($index > 0 || $share < 1.0)
                    ? $invoice->term->starts_on->copy()->addDays(mt_rand(min(14, $elapsed), $elapsed))
                    : $invoice->term->starts_on->copy()->subDays(5)->addDays(mt_rand(0, 20));
                $paidAt->setTime(mt_rand(8, 16), mt_rand(0, 59));

                if ($paidAt->isFuture()) {
                    $paidAt = now()->subDays(mt_rand(1, 6));
                }

                $method = $this->people->pick([PaymentMethod::Transfer, PaymentMethod::Transfer, PaymentMethod::Transfer, PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::Pos, PaymentMethod::Paystack]);
                $reference = match ($method) {
                    PaymentMethod::Transfer => $this->people->pick(['GTB', 'ZEN', 'UBA', 'ACC', 'FBN', 'OPY']).'-'.mt_rand(100000, 999999),
                    PaymentMethod::Pos => 'POS '.mt_rand(10000, 99999),
                    PaymentMethod::Paystack => StartOnlinePayment::reference($this->school->id),
                    default => null,
                };

                $record->handle($invoice->student, $instalment, $method, $paidAt, $bursar, $reference);
            }
        }
    }

    /**
     * The class register for every school day of this term so far (mid-term
     * break excluded). Most students are there most days; a few are often late.
     */
    public function attendance(): void
    {
        mt_srand(9090 + $this->school->id);

        $term = Term::current();
        $days = [];

        for ($day = $term->starts_on->copy(); $day->lte(today()); $day->addDay()) {
            $onBreak = $term->mid_term_starts_on && $day->between($term->mid_term_starts_on, $term->mid_term_ends_on ?? $term->mid_term_starts_on);

            if (! $day->isWeekend() && ! $onBreak) {
                $days[] = $day->toDateString();
            }
        }

        $now = now();
        $rows = [];

        foreach (Enrolment::query()->where('academic_session_id', $term->academic_session_id)->get(['id', 'class_arm_id', 'student_id']) as $enrolment) {
            $habit = ($enrolment->student_id * 37) % 10;

            foreach ($days as $date) {
                if ($date === today()->toDateString() && $enrolment->class_arm_id % 4 === 0) {
                    continue;
                }

                $roll = mt_rand(1, 100);
                $rows[] = [
                    'school_id' => $this->school->id,
                    'term_id' => $term->id,
                    'class_arm_id' => $enrolment->class_arm_id,
                    'enrolment_id' => $enrolment->id,
                    'date' => $date,
                    'status' => match (true) {
                        $roll <= 88 - $habit => 'present',
                        $roll <= 94 - $habit / 2 => 'late',
                        $roll <= 99 => 'absent',
                        default => 'excused',
                    },
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 2000) {
                    DB::table('attendance_records')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('attendance_records')->insert($rows);
        }
    }

    /**
     * Staff register for every school day of this term so far. Most staff are
     * present; a few are occasionally late or away.
     */
    public function staffAttendance(): void
    {
        mt_srand(9191 + $this->school->id);

        $term = Term::current();
        $days = [];

        for ($day = $term->starts_on->copy(); $day->lte(today()); $day->addDay()) {
            $onBreak = $term->mid_term_starts_on && $day->between($term->mid_term_starts_on, $term->mid_term_ends_on ?? $term->mid_term_starts_on);

            if (! $day->isWeekend() && ! $onBreak) {
                $days[] = $day->toDateString();
            }
        }

        $now = now();
        $rows = [];

        foreach (Staff::query()->active()->get(['id']) as $member) {
            $habit = ($member->id * 41) % 8;

            foreach ($days as $date) {
                if ($date === today()->toDateString() && $member->id % 5 === 0) {
                    continue;
                }

                $roll = mt_rand(1, 100);
                $rows[] = [
                    'school_id' => $this->school->id,
                    'term_id' => $term->id,
                    'staff_id' => $member->id,
                    'date' => $date,
                    'status' => match (true) {
                        $roll <= 90 - $habit => 'present',
                        $roll <= 95 - $habit / 2 => 'late',
                        $roll <= 99 => 'absent',
                        default => 'excused',
                    },
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 2000) {
                    DB::table('staff_attendance_records')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('staff_attendance_records')->insert($rows);
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

    /**
     * This term's assignments, quizzes, hand-ins and attempts. $busyTeacher gets a fresh pile to mark.
     */
    public function classwork(?string $busyTeacher = null): void
    {
        (new DemoClasswork($this->school->id))->build($this->currentTerm(), $busyTeacher ? $this->staffMember($busyTeacher)->id : null);
    }

    /**
     * A standard bell schedule, and every class's week filled without teacher clashes.
     */
    public function timetable(): void
    {
        foreach (Period::DEFAULTS as $position => [$name, $starts, $ends, $kind]) {
            Period::query()->create(['name' => $name, 'starts_at' => $starts, 'ends_at' => $ends, 'kind' => $kind, 'position' => $position + 1]);
        }

        $autofill = app(AutofillTimetable::class);

        ClassArm::query()->with('level')->whereHas('enrolments', fn ($query) => $query->where('academic_session_id', $this->currentTerm()->academic_session_id))->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->each(fn (ClassArm $arm) => $autofill->handle($arm));
    }

    /**
     * Notices on the board, spending through the year, and this term's exam timetable drafted.
     */
    public function office(string $proprietor, string $principal, string $bursar): void
    {
        $term = $this->currentTerm();
        $by = fn (string $key) => $this->staffMember($key)->user_id;

        foreach ([
            [$principal, 'Exam timetable is out', "The first term examinations start on {$term->ends_on->copy()->subWeeks(2)->startOfWeek()->format('l j F')}. The full timetable is on the portal under Exams. Students should come early with writing materials.", 'everyone', true, 1],
            [$proprietor, 'PTA meeting this Saturday', 'All parents are invited to the termly PTA meeting on Saturday at 10am in the main hall. We will discuss the new school bus routes and the inter-house sports.', 'parents', false, 3],
            [$principal, 'Staff briefing on Monday', 'Short staff briefing at 7:15am on Monday in the staff room. Form teachers, please bring your register summaries.', 'staff', false, 4],
            [$principal, 'Inter-house sports: training schedule', 'House training holds on Tuesdays and Thursdays after school until 4pm. Bring your house T-shirt and trainers.', 'students', false, 6],
            [$proprietor, 'Mid-term break', "School closes for mid-term on {$term->mid_term_starts_on?->format('l j F')} and resumes on {$term->mid_term_ends_on?->copy()->addDay()->format('l j F')}.", 'everyone', false, 25],
        ] as [$author, $title, $body, $audience, $pinned, $daysAgo]) {
            Announcement::query()->create([
                'title' => $title, 'body' => $body, 'audience' => $audience, 'is_pinned' => $pinned,
                'published_at' => now()->subDays($daysAgo)->setTime(9, 30), 'created_by' => $by($author),
            ]);
        }

        mt_srand(4040 + $this->school->id);
        $rows = [];
        $bursarId = $by($bursar);

        foreach (Term::query()->where('starts_on', '<=', today())->get() as $pastTerm) {
            $period = CashflowPeriod::forTerm($pastTerm);
            $last = min(today(), $pastTerm->ends_on);

            for ($day = $period->from->copy(); $day->lte($last); $day->addDay()) {
                $spends = [];

                if ($day->day === 25) {
                    $spends[] = ['salaries', 'Staff salaries for '.$day->format('F'), mt_rand(380, 460) * 10_000_00, 'Staff payroll', 'transfer'];
                }

                if ($day->isMonday() && ! $day->isSameDay($period->from)) {
                    $spends[] = ['power', mt_rand(150, 250).' litres of diesel for the generator', mt_rand(16, 26) * 10_000_00, 'Total filling station', 'cash'];
                }

                if ($day->isWednesday() && mt_rand(1, 3) === 1) {
                    $spends[] = $this->people->pick([
                        ['maintenance', 'Plumbing repairs, girls’ toilets', mt_rand(3, 9) * 10_000_00, 'Mallam Idris (plumber)', 'cash'],
                        ['stationery', 'Printing of exam papers', mt_rand(4, 12) * 10_000_00, 'Gracefield Prints', 'transfer'],
                        ['teaching_materials', 'Chemistry lab reagents', mt_rand(5, 15) * 10_000_00, 'Bio-Lab Supplies Ltd', 'transfer'],
                        ['transport', 'School bus servicing', mt_rand(6, 14) * 10_000_00, 'Peugeot workshop, Kubwa', 'pos'],
                        ['food', 'Kitchen provisions', mt_rand(4, 10) * 10_000_00, 'Wuse market', 'cash'],
                        ['events', 'Inter-house sports medals and trophies', mt_rand(5, 12) * 10_000_00, 'Award Centre', 'transfer'],
                    ]);
                }

                if ($day->day === 5 && $day->month === 10) {
                    $spends[] = ['exam_fees', 'NECO BECE registration', 120 * 15_000_00, 'NECO', 'transfer'];
                }

                foreach ($spends as [$category, $description, $amount, $paidTo, $method]) {
                    $rows[] = [
                        'school_id' => $this->school->id, 'ulid' => strtolower((string) Str::ulid()), 'term_id' => $pastTerm->id,
                        'category' => $category, 'description' => $description, 'amount' => $amount, 'spent_on' => $day->toDateString(),
                        'paid_to' => $paidTo, 'method' => $method, 'recorded_by' => $bursarId, 'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        }

        DB::table('expenses')->insert($rows);

        app(GenerateExamTimetable::class)->handle($term, $term->ends_on->copy()->subWeeks(2)->startOfWeek(), ['08:30', '11:30']);
        ExamPaper::query()->where('term_id', $term->id)->with('classLevel')->get()->each(fn (ExamPaper $paper) => $paper->update([
            'venue' => in_array($paper->classLevel->section, [ClassSection::Junior, ClassSection::Senior], true) ? 'Main hall' : $paper->classLevel->name.' classroom',
        ]));
    }

    /**
     * A crest for the school, so printed documents show a school logo next to Ta'aleem's.
     */
    public function logo(string $colour, string $accent): void
    {
        $path = "schools/{$this->school->ulid}/logo-demo.svg";
        Storage::disk('public')->put($path, DemoFiles::crest($this->school->initials(), $colour, $accent));
        $this->school->update(['logo_path' => $path]);
    }

    /**
     * Uniforms, books and stationery in the store, with some low-stock lines and a few sales.
     */
    public function inventory(?string $storeKeeper = 'store'): void
    {
        $recorder = $this->staffMember($storeKeeper)->user;
        $move = app(RecordStockMovement::class);

        $catalogue = [
            ['Senior school shirt (white)', 'UNI-SHIRT-W', InventoryCategory::Uniforms, 'piece', 4_500_00, 80, 15],
            ['Senior school shirt (blue)', 'UNI-SHIRT-B', InventoryCategory::Uniforms, 'piece', 4_500_00, 12, 15],
            ['House T-shirt', 'UNI-HOUSE', InventoryCategory::Uniforms, 'piece', 2_500_00, 45, 20],
            ['New General Mathematics JSS 1', 'BK-MTH-J1', InventoryCategory::Textbooks, 'piece', 3_200_00, 60, 10],
            ['New General Mathematics JSS 2', 'BK-MTH-J2', InventoryCategory::Textbooks, 'piece', 3_400_00, 8, 10],
            ['Comprehensive English JSS 2', 'BK-ENG-J2', InventoryCategory::Textbooks, 'piece', 2_800_00, 35, 10],
            ['Exercise book (60 leaves)', 'STN-EX-60', InventoryCategory::Stationery, 'piece', 350_00, 200, 40],
            ['Graph book', 'STN-GRAPH', InventoryCategory::Stationery, 'piece', 450_00, 3, 20],
            ['Lab coat', 'LAB-COAT', InventoryCategory::Lab, 'piece', 6_500_00, 18, 5],
            ['Football (size 5)', 'SPT-BALL', InventoryCategory::Sports, 'piece', 8_000_00, 6, 4],
        ];

        foreach ($catalogue as [$name, $sku, $category, $unit, $cost, $qty, $reorder]) {
            $item = InventoryItem::query()->create([
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'unit' => $unit,
                'unit_cost' => $cost,
                'reorder_level' => $reorder,
                'quantity_on_hand' => 0,
                'is_active' => true,
            ]);

            $move->handle($item, [
                'type' => StockMovementType::Receive->value,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'note' => 'Opening stock for the term',
                'moved_at' => now()->subWeeks(3),
            ], $recorder);
        }

        $shirt = InventoryItem::query()->where('sku', 'UNI-SHIRT-W')->first();
        $book = InventoryItem::query()->where('sku', 'BK-MTH-J2')->first();
        $students = Student::query()->orderBy('id')->limit(4)->get();

        if ($shirt && $students->isNotEmpty()) {
            $move->handle($shirt, [
                'type' => StockMovementType::Sale->value,
                'quantity' => 1,
                'student_id' => $students[0]->id,
                'note' => 'Sold at the store',
                'moved_at' => now()->subDays(5),
            ], $recorder);
        }

        if ($book && $students->count() > 1) {
            $move->handle($book, [
                'type' => StockMovementType::Issue->value,
                'quantity' => 2,
                'note' => 'Issued to JSS 2A maths',
                'moved_at' => now()->subDays(2),
            ], $recorder);
        }
    }

    /**
     * Last month’s payroll prepared and marked paid, so the bursar has a history to open.
     */
    public function payroll(?string $bursar = 'bursar'): void
    {
        $user = $this->staffMember($bursar)->user;
        $month = now()->subMonthNoOverflow();

        if (Staff::query()->active()->withSalary()->doesntExist()) {
            return;
        }

        if (PayrollRun::query()->counted()->where('year', $month->year)->where('month', $month->month)->exists()) {
            return;
        }

        ['run' => $run] = app(PreparePayroll::class)->handle($month->year, $month->month, $user);
        $run->lines()->update(['paid_at' => $month->copy()->endOfMonth()->setTime(14, 0)]);
        $run->update([
            'status' => PayrollRunStatus::Paid,
            'paid_at' => $month->copy()->endOfMonth()->setTime(14, 0),
        ]);
    }
}
