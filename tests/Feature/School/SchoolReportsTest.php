<?php

use App\Actions\Academic\StartSession;
use App\Actions\Fees\GenerateInvoices;
use App\Actions\Fees\RecordPayment;
use App\Actions\Inventory\RecordStockMovement;
use App\Enums\ApplicantStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EnrolmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SchoolRole;
use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\AssessmentComponent;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\Expense;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\InventoryItem;
use App\Models\Period;
use App\Models\Score;
use App\Models\Student;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Support\Reports\ReportCatalogue;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->admin = memberOf($this->school, SchoolRole::SchoolAdmin, ['name' => 'Grace Udo']);
    $this->principal = memberOf($this->school, SchoolRole::Principal);
    $this->head = memberOf($this->school, SchoolRole::HeadTeacher);

    inSchool($this->school, function () {
        $this->term = Term::current();
        $this->jss1a = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
    });

    $this->report = fn (string $key, array $query = []) => $this->actingAs($this->admin)->get(route('school.reports.show', [$this->school, $key, ...$query]));
    $this->figures = fn (string $key, array $query = []) => ($this->report)($key, $query)->assertOk()->viewData('figures');
    $this->csv = fn (string $key, array $query = []) => ($this->report)($key, [...$query, 'export' => 'csv'])->assertOk()->streamedContent();
});

it('lets school admins open the reports hub and each report', function () {
    $this->actingAs($this->admin)->get(route('school.reports.index', $this->school))
        ->assertOk()
        ->assertSee('Enrolment & demographics')
        ->assertSee('Academic performance')
        ->assertSee('Fees & collections')
        ->assertSee('Stores & inventory');
});

it('shows every report on screen, on the letterhead and as a spreadsheet, even before there is data', function (string $report) {
    $title = ReportCatalogue::find($report)['title'];

    $this->actingAs($this->admin)->get(route('school.reports.show', [$this->school, $report]))->assertOk()->assertSee($title);
    $this->actingAs($this->admin)->get(route('school.reports.print', [$this->school, $report]))->assertOk()->assertSee($this->school->name)->assertSee('Proprietor / Principal');
    expect(($this->csv)($report))->toStartWith("\xEF\xBB\xBF");
})->with(fn () => ReportCatalogue::keys());

it('opens each report to the roles that hold its permission', function (SchoolRole $role, array $allowed) {
    $user = $role === SchoolRole::SchoolAdmin ? $this->admin : memberOf($this->school, $role);

    $this->actingAs($user)->get(route('school.reports.index', $this->school))->assertOk();

    foreach (ReportCatalogue::keys() as $report) {
        $this->actingAs($user)->get(route('school.reports.show', [$this->school, $report]))->assertStatus(in_array($report, $allowed, true) ? 200 : 403);
    }

    $this->actingAs($user)->get(route('school.reports.print', [$this->school, $allowed[0]]))->assertOk();
})->with([
    'school admin' => [SchoolRole::SchoolAdmin, ['enrolment', 'academic', 'attendance', 'admissions', 'fees', 'finance', 'inventory', 'staff']],
    'principal' => [SchoolRole::Principal, ['enrolment', 'academic', 'attendance', 'admissions', 'staff']],
    'head teacher' => [SchoolRole::HeadTeacher, ['enrolment', 'academic', 'attendance', 'admissions']],
    'bursar' => [SchoolRole::Bursar, ['fees', 'finance']],
    'store keeper' => [SchoolRole::StoreKeeper, ['inventory']],
]);

it('lists only the reports a principal may open, and keeps teachers out', function () {
    $this->actingAs($this->principal)->get(route('school.reports.index', $this->school))
        ->assertSee('Academic performance')
        ->assertSee('Staff & workload')
        ->assertDontSee('Fees & collections')
        ->assertDontSee('Income & expenditure');

    $this->actingAs(memberOf($this->school, SchoolRole::Teacher))->get(route('school.reports.index', $this->school))->assertForbidden();
});

it('shows the staff report without pay to people who do not run payroll', function () {
    $teacher = staffOf($this->school, SchoolRole::Teacher, ['first_name' => 'Usman', 'last_name' => 'Lawal']);
    inSchool($this->school, fn () => $teacher->update(['basic_salary' => 100_000_00]));

    $this->actingAs($this->principal)->get(route('school.reports.show', [$this->school, 'staff']))
        ->assertOk()
        ->assertSee('Average service')
        ->assertDontSee('Monthly wage bill')
        ->assertDontSee('₦100,000');
    expect($this->actingAs($this->principal)->get(route('school.reports.show', [$this->school, 'staff', 'export' => 'csv']))->streamedContent())
        ->toContain('"Usman Lawal"')
        ->not->toContain('Net monthly pay');

    $this->actingAs($this->admin)->get(route('school.reports.show', [$this->school, 'staff']))->assertSee('Monthly wage bill')->assertSee('₦100,000');
});

it('refuses unknown reports and periods that belong to another school', function () {
    $other = schoolWithStructure(['slug' => 'royal-crest']);
    [$otherTerm, $otherSession] = inSchool($other, fn () => [Term::current(), AcademicSession::current()]);

    $this->actingAs($this->admin)->get('/app/greenfield/reports/salaries')->assertNotFound();
    ($this->report)('fees', ['term' => $otherTerm->id])->assertNotFound();
    ($this->report)('enrolment', ['session' => $otherSession->id])->assertNotFound();
});

it('counts who is on roll by class and gender, new intake against returning, and leaves out withdrawals', function () {
    $aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello', 'gender' => 'female', 'state_of_origin' => 'Kano', 'religion' => 'Islam'], ['phone' => '+2348031112222'])->student;
    enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Chidi', 'last_name' => 'Okafor', 'gender' => 'male']);
    $tunde = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Tunde', 'last_name' => 'Ade', 'gender' => 'male'])->student;

    inSchool($this->school, function () use ($aisha, $tunde) {
        $tunde->currentEnrolment->update(['status' => EnrolmentStatus::Withdrawn]);
        $lastSession = app(StartSession::class)->handle(2025, makeCurrent: false);
        $aisha->enrolments()->create(['academic_session_id' => $lastSession->id, 'class_arm_id' => $this->jss1a->id, 'status' => EnrolmentStatus::Promoted]);
    });

    $figures = ($this->figures)('enrolment');

    expect($figures['stats'])->toMatchArray(['on_roll' => 2, 'boys' => 1, 'girls' => 1, 'new' => 1, 'withdrawn' => 1])
        ->and($figures['classes']->sole())->toMatchArray(['total' => 2, 'new' => 1])
        ->and($figures['states'])->toMatchArray(['Kano' => 1, 'Not recorded' => 1])
        ->and(collect($figures['trend'])->pluck('students', 'session')->all())->toBe(['2025/2026' => 1, '2026/2027' => 2]);

    expect(($this->csv)('enrolment'))
        ->toContain('Bello,Aisha')
        ->toContain('Returning')
        ->toContain('+2348031112222')
        ->not->toContain('Tunde');
});

it('ranks classes and subjects, and flags students below the pass mark overall or in three subjects', function () {
    $students = collect(['Adamu', 'Bello', 'Chukwu', 'Danjuma'])
        ->mapWithKeys(fn (string $surname) => [$surname => enrol($this->school, 'JSS 1', 'A', ['last_name' => $surname])->student]);

    inSchool($this->school, function () use ($students) {
        $components = AssessmentComponent::query()->orderBy('position')->get();
        $subjects = $this->jss1a->classSubjects()->with('subject')->get()->sortBy('subject.name')->values();
        $maths = $subjects->firstWhere('subject.code', 'MTH');
        $others = $subjects->reject(fn (ClassSubject $classSubject) => $classSubject->is($maths))->values();

        // Every component of a subject, split by each component's share of 100.
        $score = function (Student $student, ClassSubject $classSubject, int $total) use ($components): void {
            foreach ($components as $component) {
                Score::query()->create([
                    'term_id' => $this->term->id,
                    'enrolment_id' => $student->currentEnrolment->id,
                    'class_subject_id' => $classSubject->id,
                    'assessment_component_id' => $component->id,
                    'score' => $total * $component->max_score / 100,
                ]);
            }
        };

        // Adamu: 90 in every subject. Bello: 30, 30, 20. Chukwu: 70 in maths only.
        // Danjuma: 100 in maths but 30 in three others, so a fine average with three failures.
        $subjects->each(fn (ClassSubject $classSubject) => $score($students['Adamu'], $classSubject, 90));
        $score($students['Bello'], $maths, 30);
        $score($students['Bello'], $others[0], 30);
        $score($students['Bello'], $others[1], 20);
        $score($students['Chukwu'], $maths, 70);
        $score($students['Danjuma'], $maths, 100);
        $others->take(3)->each(fn (ClassSubject $classSubject) => $score($students['Danjuma'], $classSubject, 30));
    });

    $figures = ($this->figures)('academic');

    expect($figures['stats'])->toMatchArray(['scored' => 4, 'pass_rate' => 75])
        ->and($figures['subjects']->firstWhere('subject', 'Mathematics'))->toMatchArray(['entries' => 4, 'average' => 72.5, 'pass_rate' => 75, 'credit_rate' => 75])
        ->and($figures['atRisk']->pluck('name')->map(fn (string $name) => strtok($name, ' '))->all())->toBe(['BELLO', 'DANJUMA'])
        ->and($figures['atRisk']->last())->toMatchArray(['failed' => 3, 'percent' => 47.5])
        // Only Adamu is scored in at least half his subjects, so only he can top the school.
        ->and($figures['top']->pluck('name')->map(fn (string $name) => strtok($name, ' '))->all())->toBe(['ADAMU']);

    expect(($this->csv)('academic'))->toContain('"BELLO')->toContain('Subjects below pass mark');
});

it('reports what was billed, collected and still owed, by class, item, age of debt and who took the money', function () {
    $aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'])->student;
    $chidi = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Chidi', 'last_name' => 'Okafor'])->student;

    inSchool($this->school, function () use ($aisha, $chidi) {
        $tuition = FeeItem::query()->create(['name' => 'Tuition', 'position' => 1]);
        FeeStructure::query()->create(['term_id' => $this->term->id, 'class_level_id' => ClassLevel::query()->where('name', 'JSS 1')->value('id'), 'fee_item_id' => $tuition->id, 'amount' => 150_000_00]);
        app(GenerateInvoices::class)->handle($this->term, dueOn: today()->subDays(45));
        app(RecordPayment::class)->handle($aisha, 150_000_00, PaymentMethod::Transfer, now(), $this->admin);
        app(RecordPayment::class)->handle($chidi, 50_000_00, PaymentMethod::Cash, now(), $this->admin);
    });

    $figures = ($this->figures)('fees');

    expect($figures['stats'])->toMatchArray(['billed' => 300_000_00, 'paid' => 200_000_00, 'outstanding' => 100_000_00, 'rate' => 67, 'fully_paid' => 1, 'part_paid' => 1, 'unpaid' => 0, 'not_invoiced' => 0])
        ->and($figures['classes']->sole())->toMatchArray(['billed' => 300_000_00, 'owing' => 1, 'rate' => 67])
        ->and($figures['items']->sole())->toMatchArray(['name' => 'Tuition', 'students' => 2, 'amount' => 300_000_00])
        ->and(collect($figures['ageing'])->firstWhere('label', '31–60 days overdue'))->toMatchArray(['invoices' => 1, 'amount' => 100_000_00])
        ->and($figures['recorders'])->toBe([['name' => 'Grace Udo', 'payments' => 2, 'amount' => 200_000_00]])
        ->and($figures['largest']->sole()->student->is($chidi))->toBeTrue();

    expect(($this->csv)('fees'))->toContain('"OKAFOR Chidi"')->toContain(',150000,50000,100000,');
});

it('sets fees in against spending out and compares the term with the one before', function () {
    $aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'])->student;

    inSchool($this->school, function () use ($aisha) {
        app(RecordPayment::class)->handle($aisha, 200_000_00, PaymentMethod::Cash, now(), $this->admin);
        Expense::query()->create(['category' => 'power', 'description' => 'Diesel for the generator', 'amount' => 50_000_00, 'spent_on' => today(), 'method' => 'cash']);
        Expense::query()->create(['category' => 'power', 'description' => 'Voided diesel', 'amount' => 90_000_00, 'spent_on' => today(), 'method' => 'cash', 'voided_at' => now()]);
    });

    $figures = ($this->figures)('finance');

    expect($figures['stats'])->toMatchArray(['income' => 200_000_00, 'spending' => 50_000_00, 'net' => 150_000_00, 'margin' => 75])
        ->and(collect($figures['comparison'])->firstWhere('current', true))->toMatchArray(['income' => 200_000_00, 'net' => 150_000_00]);

    expect(($this->csv)('finance'))->toContain('Diesel for the generator')->toContain('-50000')->not->toContain('Voided diesel');
});

it('breaks attendance down by week and weekday and counts chronic absentees', function () {
    $aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'])->student;
    $chidi = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Chidi', 'last_name' => 'Okafor'])->student;

    inSchool($this->school, function () use ($aisha, $chidi) {
        $monday = $this->term->starts_on->copy()->next(Carbon::MONDAY);

        foreach (range(0, 4) as $offset) {
            foreach ([[$aisha, AttendanceStatus::Present], [$chidi, $offset === 0 ? AttendanceStatus::Present : AttendanceStatus::Absent]] as [$student, $status]) {
                AttendanceRecord::query()->create([
                    'term_id' => $this->term->id,
                    'class_arm_id' => $this->jss1a->id,
                    'enrolment_id' => $student->currentEnrolment->id,
                    'date' => $monday->copy()->addDays($offset),
                    'status' => $status,
                ]);
            }
        }
    });

    $figures = ($this->figures)('attendance');
    $weekdays = collect($figures['weekdays'])->pluck('rate', 'label');

    expect($figures['students'])->toMatchArray(['marked' => 10, 'absent' => 4, 'rate' => 60])
        ->and($figures['absenteeCount'])->toBe(1)
        ->and($figures['days'])->toBe(5)
        ->and(collect($figures['weeks'])->sum('absent'))->toBe(4)
        ->and($weekdays['Monday'])->toBe(100)
        ->and($weekdays['Tuesday'])->toBe(50);

    expect(($this->csv)('attendance'))->toContain('"OKAFOR Chidi",5,1,0,4,0,20');
});

it('follows applications from form to enrolment', function () {
    inSchool($this->school, function () {
        $jss1 = ClassLevel::query()->where('name', 'JSS 1')->sole();

        foreach ([[ApplicantStatus::Enrolled, 70], [ApplicantStatus::Offered, 60], [ApplicantStatus::Rejected, 30], [ApplicantStatus::Submitted, null]] as [$status, $score]) {
            Applicant::query()->create([
                'application_no' => 'APP/'.fake()->unique()->numerify('####'),
                'academic_session_id' => AcademicSession::current()->id,
                'class_level_id' => $jss1->id,
                'status' => $status,
                'source' => 'online',
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'gender' => 'female',
                'guardian_first_name' => 'Halima',
                'guardian_last_name' => 'Yusuf',
                'guardian_relationship' => 'mother',
                'guardian_phone' => '+2348055551212',
                'exam_score' => $score,
            ]);
        }
    });

    $figures = ($this->figures)('admissions');

    expect($figures['stats'])->toMatchArray(['applications' => 4, 'examined' => 3, 'offered' => 2, 'enrolled' => 1, 'rejected' => 1, 'conversion' => 25, 'acceptance' => 50, 'average_score' => 53.3])
        ->and(collect($figures['funnel'])->pluck('count')->all())->toBe([4, 3, 2, 1])
        ->and($figures['scores'])->toMatchArray(['Below 40' => 1, '60–69' => 1, '70–79' => 1])
        ->and($figures['levels']->sole())->toMatchArray(['applied' => 4, 'offered' => 2, 'enrolled' => 1]);
});

it('values the store at cost, lists what to reorder and what moved this term', function () {
    inSchool($this->school, function () {
        $shirt = InventoryItem::query()->create(['name' => 'School shirt', 'category' => 'uniforms', 'unit' => 'piece', 'quantity_on_hand' => 10, 'reorder_level' => 5, 'unit_cost' => 1_000_00]);
        InventoryItem::query()->create(['name' => 'Graph book', 'category' => 'stationery', 'unit' => 'piece', 'quantity_on_hand' => 2, 'reorder_level' => 5, 'unit_cost' => 500_00]);
        InventoryItem::query()->create(['name' => 'Lab coat', 'category' => 'lab', 'unit' => 'piece', 'quantity_on_hand' => 0]);
        app(RecordStockMovement::class)->handle($shirt, ['type' => 'issue', 'quantity' => 3, 'unit_cost' => 1_000_00], $this->admin);
    });

    $figures = ($this->figures)('inventory');

    expect($figures['stats'])->toMatchArray(['items' => 3, 'units' => 9, 'value' => 8_000_00, 'low' => 1, 'out' => 1, 'uncosted' => 1])
        ->and(collect($figures['movements'])->firstWhere('type.value', 'issue'))->toMatchArray(['count' => 1, 'units' => 3, 'value' => 3_000_00])
        ->and($figures['busiest']->sole())->toMatchArray(['issued' => 3, 'out' => 3])
        ->and($figures['reorder']->pluck('name')->all())->toBe(['Graph book']);

    expect(($this->csv)('inventory'))->toContain('"School shirt",,Uniforms,piece,7,5,1000,7000,0,3,0,Active');
});

it('adds up the wage bill, teaching load and subjects nobody teaches', function () {
    $teacher = staffOf($this->school, SchoolRole::Teacher, ['first_name' => 'Usman', 'last_name' => 'Lawal', 'gender' => 'male', 'department' => 'Sciences']);
    staffOf($this->school, SchoolRole::Bursar, ['first_name' => 'Ada', 'last_name' => 'Eze', 'department' => 'Accounts']);

    inSchool($this->school, function () use ($teacher) {
        $teacher->update(['basic_salary' => 100_000_00, 'allowances' => 20_000_00, 'deductions' => 10_000_00, 'date_employed' => today()->subYears(4)]);
        $maths = $this->jss1a->classSubjects()->whereHas('subject', fn ($query) => $query->where('code', 'MTH'))->sole();
        $maths->update(['teacher_id' => $teacher->id]);

        foreach ([1, 2] as $position) {
            $period = Period::query()->create(['name' => "Period {$position}", 'starts_at' => '08:00', 'ends_at' => '08:40', 'kind' => 'lesson', 'position' => $position]);
            TimetableEntry::query()->create(['class_arm_id' => $this->jss1a->id, 'period_id' => $period->id, 'day' => 1, 'class_subject_id' => $maths->id]);
        }
    });

    $figures = ($this->figures)('staff');
    $unassigned = inSchool($this->school, fn () => ClassSubject::query()->whereNull('teacher_id')->count());

    expect($figures['stats'])->toMatchArray(['active' => 2, 'teaching' => 1, 'non_teaching' => 1, 'men' => 1, 'women' => 1, 'wage_bill' => 110_000_00, 'unsalaried' => 1])
        ->and($figures['load']->sole())->toMatchArray(['subjects' => 1, 'classes' => 1, 'periods' => 2])
        ->and($figures['unassigned'])->toHaveCount($unassigned)
        ->and($figures['service'])->toMatchArray(['3–5 years' => 1, 'Not recorded' => 1]);

    expect(($this->csv)('staff'))->toContain('"Usman Lawal"')->toContain('110000');
});
