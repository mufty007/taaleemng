<?php

use App\Actions\Platform\AddSchoolMember;
use App\Enums\SchoolRole;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Applicant;
use App\Models\AssessmentComponent;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\CommentTemplate;
use App\Models\ExamPaper;
use App\Models\Expense;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\GradingScheme;
use App\Models\Guardian;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Period;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReportTrait;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Score;
use App\Models\Staff;
use App\Models\StockMovement;
use App\Models\Stream;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\Subject;
use App\Models\TermReport;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\MissingSchoolContextException;
use App\Support\Tenancy\SchoolAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/*
 * Every tenant model gets a dataset row here. Each test seeds the model for
 * two schools and proves one school can never see or touch the other's rows.
 */
dataset('tenant models', [
    'school settings' => [fn () => SchoolSetting::query()->create(['key' => 'motto_colour', 'value' => 'green'])],
    'number sequences' => [fn () => NumberSequence::query()->create(['key' => 'invoice_no', 'pattern' => 'INV/{SEQ:4}'])],
    'academic sessions' => [fn () => AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23'])],
    'terms' => [fn () => AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23'])
        ->terms()->create(['sequence' => 1, 'name' => 'First Term', 'starts_on' => '2026-09-14', 'ends_on' => '2026-12-18'])],
    'class levels' => [fn () => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])],
    'class arms' => [fn () => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->arms()->create(['name' => 'A'])],
    'streams' => [fn () => Stream::query()->create(['name' => 'Science'])],
    'subjects' => [fn () => Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH', 'category' => 'core'])],
    'class subjects' => [fn () => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->arms()->create(['name' => 'A'])
        ->classSubjects()->create(['subject_id' => Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH', 'category' => 'core'])->id])],
    'staff' => [fn () => Staff::query()->create(['staff_no' => 'STF/001', 'first_name' => 'Emeka', 'last_name' => 'Okonkwo', 'gender' => 'male', 'designation' => 'Teacher'])],
    'students' => [fn () => Student::query()->create(['admission_no' => 'ADM/001', 'first_name' => 'Aisha', 'last_name' => 'Bello', 'gender' => 'female'])],
    'guardians' => [fn () => Guardian::query()->create(['first_name' => 'Amina', 'last_name' => 'Bello', 'relationship' => 'mother', 'phone' => '+234802'.random_int(1000000, 9999999)])],
    'enrolments' => [fn () => Student::query()->create(['admission_no' => 'ADM/001', 'first_name' => 'Aisha', 'last_name' => 'Bello', 'gender' => 'female'])->enrolments()->create([
        'academic_session_id' => AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23'])->id,
        'class_arm_id' => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->arms()->create(['name' => 'A'])->id,
    ])],
    'assessment components' => [fn () => AssessmentComponent::query()->create(['name' => 'First CA', 'short_name' => 'CA1', 'max_score' => 20])],
    'grading schemes' => [fn () => GradingScheme::query()->create(['name' => 'Basic', 'bands' => [['min' => 0, 'grade' => 'F', 'remark' => 'Fail']]])],
    'scores' => [function () {
        $session = AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23']);
        $arm = ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->arms()->create(['name' => 'A']);
        $student = Student::query()->create(['admission_no' => 'ADM/001', 'first_name' => 'Aisha', 'last_name' => 'Bello', 'gender' => 'female']);

        return Score::query()->create([
            'term_id' => $session->terms()->create(['sequence' => 1, 'name' => 'First Term', 'starts_on' => '2026-09-14', 'ends_on' => '2026-12-18'])->id,
            'enrolment_id' => $student->enrolments()->create(['academic_session_id' => $session->id, 'class_arm_id' => $arm->id])->id,
            'class_subject_id' => $arm->classSubjects()->create(['subject_id' => Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH', 'category' => 'core'])->id])->id,
            'assessment_component_id' => AssessmentComponent::query()->create(['name' => 'First CA', 'short_name' => 'CA1', 'max_score' => 20])->id,
            'score' => 15,
        ]);
    }],
    'applicants' => [fn () => Applicant::query()->create([
        'application_no' => 'APP/2026/0001',
        'academic_session_id' => AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23'])->id,
        'class_level_id' => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->id,
        'status' => 'submitted', 'source' => 'online', 'first_name' => 'Zainab', 'last_name' => 'Yusuf', 'gender' => 'female',
        'guardian_first_name' => 'Halima', 'guardian_last_name' => 'Yusuf', 'guardian_relationship' => 'mother', 'guardian_phone' => '+2348055551212',
    ])],
    'attendance records' => [function () {
        $invoice = isolationInvoice();

        return AttendanceRecord::query()->create([
            'term_id' => $invoice->term_id, 'class_arm_id' => $invoice->enrolment->class_arm_id, 'enrolment_id' => $invoice->enrolment_id,
            'date' => '2026-09-15', 'status' => 'present',
        ]);
    }],
    'fee items' => [fn () => FeeItem::query()->create(['name' => 'Tuition'])],
    'fee structures' => [fn () => FeeStructure::query()->create([
        'term_id' => isolationInvoice()->term_id,
        'class_level_id' => ClassLevel::query()->create(['name' => 'JSS 2', 'short_name' => 'JSS 2', 'section' => 'junior'])->id,
        'fee_item_id' => FeeItem::query()->create(['name' => 'Tuition'])->id,
        'amount' => 150_000_00,
    ])],
    'invoices' => [fn () => isolationInvoice()],
    'invoice lines' => [fn () => isolationInvoice()->lines()->create(['description' => 'Tuition', 'amount' => 150_000_00])],
    'payments' => [fn () => isolationPayment(isolationInvoice())],
    'payment allocations' => [function () {
        $invoice = isolationInvoice();

        return PaymentAllocation::query()->create(['payment_id' => isolationPayment($invoice)->id, 'invoice_id' => $invoice->id, 'amount' => 1_000_00]);
    }],
    'student imports' => [fn () => StudentImport::query()->create(['file_name' => 'jss1.csv', 'status' => 'checked', 'rows' => [], 'problems' => []])],
    'report traits' => [fn () => ReportTrait::query()->create(['name' => 'Punctuality', 'category' => 'affective'])],
    'comment templates' => [fn () => CommentTemplate::query()->create(['audience' => 'form_teacher', 'min_average' => 0, 'max_average' => 100, 'body' => 'Keep it up, {name}.'])],
    'term reports' => [function () {
        $session = AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23']);
        $student = Student::query()->create(['admission_no' => 'ADM/001', 'first_name' => 'Aisha', 'last_name' => 'Bello', 'gender' => 'female']);

        return TermReport::query()->create([
            'term_id' => $session->terms()->create(['sequence' => 1, 'name' => 'First Term', 'starts_on' => '2026-09-14', 'ends_on' => '2026-12-18'])->id,
            'enrolment_id' => $student->enrolments()->create([
                'academic_session_id' => $session->id,
                'class_arm_id' => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->arms()->create(['name' => 'A'])->id,
            ])->id,
            'teacher_comment' => 'A good term.',
        ]);
    }],
    'result publications' => [fn () => ResultPublication::query()->create([
        'term_id' => AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23'])
            ->terms()->create(['sequence' => 1, 'name' => 'First Term', 'starts_on' => '2026-09-14', 'ends_on' => '2026-12-18'])->id,
        'class_arm_id' => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->arms()->create(['name' => 'A'])->id,
        'published_at' => now(),
    ])],
    'assignments' => [fn () => isolationAssignment()],
    'assignment submissions' => [function () {
        $invoice = isolationInvoice();

        return AssignmentSubmission::query()->create([
            'assignment_id' => isolationAssignment($invoice)->id, 'student_id' => $invoice->student_id, 'body' => 'Done', 'submitted_at' => now(),
        ]);
    }],
    'quizzes' => [fn () => isolationQuiz()],
    'quiz questions' => [fn () => isolationQuiz()->questions()->create(['type' => 'true_false', 'prompt' => 'Lagos is a state.', 'answer' => ['true'], 'marks' => 1])],
    'quiz attempts' => [function () {
        $invoice = isolationInvoice();

        return QuizAttempt::query()->create(['quiz_id' => isolationQuiz($invoice)->id, 'student_id' => $invoice->student_id, 'started_at' => now()]);
    }],
    'periods' => [fn () => Period::query()->create(['name' => 'Period 1', 'starts_at' => '07:50', 'ends_at' => '08:30', 'kind' => 'lesson'])],
    'announcements' => [fn () => Announcement::query()->create(['title' => 'PTA', 'body' => 'Saturday', 'audience' => 'everyone', 'published_at' => now()])],
    'announcement reads' => [fn () => AnnouncementRead::query()->create([
        'announcement_id' => Announcement::query()->create(['title' => 'PTA', 'body' => 'Saturday', 'audience' => 'everyone', 'published_at' => now()])->id,
        'user_id' => User::factory()->create()->id, 'read_at' => now(),
    ])],
    'expenses' => [fn () => Expense::query()->create(['category' => 'power', 'description' => 'Diesel', 'amount' => 190_000_00, 'spent_on' => '2026-09-20', 'method' => 'cash'])],
    'payroll runs' => [fn () => PayrollRun::query()->create(['year' => 2026, 'month' => 10, 'status' => 'prepared', 'gross_total' => 100_000_00, 'deductions_total' => 10_000_00, 'net_total' => 90_000_00, 'headcount' => 1])],
    'payroll lines' => [function () {
        $run = PayrollRun::query()->create(['year' => 2026, 'month' => 9, 'status' => 'prepared', 'gross_total' => 100_000_00, 'deductions_total' => 0, 'net_total' => 100_000_00, 'headcount' => 1]);
        $staff = Staff::query()->create(['staff_no' => 'ISO/STF/1', 'first_name' => 'Iso', 'last_name' => 'Staff', 'gender' => 'female', 'designation' => 'Teacher', 'employment_type' => 'full_time', 'status' => 'active']);

        return PayrollLine::query()->create([
            'payroll_run_id' => $run->id, 'staff_id' => $staff->id, 'staff_name' => 'Iso Staff', 'staff_no' => 'ISO/STF/1',
            'designation' => 'Teacher', 'basic_salary' => 100_000_00, 'allowances' => 0, 'deductions' => 0, 'net_pay' => 100_000_00,
        ]);
    }],
    'inventory items' => [fn () => InventoryItem::query()->create(['name' => 'Shirt', 'category' => 'uniforms', 'unit' => 'piece', 'quantity_on_hand' => 10])],
    'stock movements' => [function () {
        $item = InventoryItem::query()->create(['name' => 'Book', 'category' => 'textbooks', 'unit' => 'piece', 'quantity_on_hand' => 5]);

        return StockMovement::query()->create([
            'inventory_item_id' => $item->id, 'type' => 'receive', 'quantity' => 5, 'balance_after' => 5, 'moved_at' => now(),
        ]);
    }],
    'exam papers' => [function () {
        $invoice = isolationInvoice();

        return ExamPaper::query()->create([
            'term_id' => $invoice->term_id, 'class_level_id' => $invoice->enrolment->classArm->class_level_id,
            'subject_id' => Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH', 'category' => 'core'])->id,
            'starts_at' => '2026-12-07 09:00', 'duration_minutes' => 120,
        ]);
    }],
    'timetable entries' => [function () {
        $classSubject = isolationClassSubject();

        return TimetableEntry::query()->create([
            'class_arm_id' => $classSubject->class_arm_id, 'class_subject_id' => $classSubject->id, 'day' => 1,
            'period_id' => Period::query()->create(['name' => 'Period 1', 'starts_at' => '07:50', 'ends_at' => '08:30', 'kind' => 'lesson'])->id,
        ]);
    }],
]);

beforeEach(function () {
    $this->schoolA = schoolWithRoles(['name' => 'School A']);
    $this->schoolB = schoolWithRoles(['name' => 'School B']);
    $this->current = app(CurrentSchool::class);
});

it('only returns rows that belong to the school in context', function (Closure $make) {
    $rowA = $this->current->run($this->schoolA, $make);
    $rowB = $this->current->run($this->schoolB, $make);

    $this->current->run($this->schoolA, function () use ($rowA, $rowB) {
        $model = $rowA::class;

        expect($model::query()->pluck('id')->all())->toBe([$rowA->id])
            ->and($model::query()->find($rowB->id))->toBeNull();
    });
})->with('tenant models');

it('stamps new rows with the school in context', function (Closure $make) {
    $row = $this->current->run($this->schoolB, $make);

    expect($row->school_id)->toBe($this->schoolB->id);
})->with('tenant models');

it('refuses to query tenant data without a school in context', function (Closure $make) {
    $row = $this->current->run($this->schoolA, $make);

    $this->current->forget();

    $row::query()->get();
})->with('tenant models')->throws(MissingSchoolContextException::class);

it('ignores a mass-assigned school_id', function () {
    $setting = $this->current->run($this->schoolA, fn () => SchoolSetting::query()->create([
        'key' => 'sneaky',
        'value' => 1,
        'school_id' => $this->schoolB->id,
    ]));

    expect($setting->school_id)->toBe($this->schoolA->id);
});

it('refuses to create a row for another school', function () {
    $this->current->run($this->schoolA, fn () => (new SchoolSetting(['key' => 'sneaky', 'value' => 1]))
        ->forceFill(['school_id' => $this->schoolB->id])
        ->save());
})->throws(LogicException::class);

it('refuses to move a row to another school', function () {
    $this->current->run($this->schoolA, function () {
        $setting = SchoolSetting::store('motto_colour', 'green');
        $setting->school_id = $this->schoolB->id;
        $setting->save();
    });
})->throws(LogicException::class);

it('restores the previous school after run()', function () {
    $this->current->set($this->schoolA);

    $inside = $this->current->run($this->schoolB, fn () => $this->current->id());

    expect($inside)->toBe($this->schoolB->id)
        ->and($this->current->id())->toBe($this->schoolA->id);
});

it('runs queued jobs inside the school that dispatched them', function () {
    $job = $this->current->run($this->schoolB, fn () => new RecordsCurrentSchoolJob);

    $this->current->forget();
    dispatch_sync($job);

    expect(RecordsCurrentSchoolJob::$ranFor)->toBe($this->schoolB->id)
        ->and($this->current->check())->toBeFalse();
});

it('returns 404 when a member of one school opens another school', function () {
    $user = memberOf($this->schoolA);

    $this->actingAs($user)->get(route('school.dashboard', $this->schoolB))->assertNotFound();
    $this->actingAs($user)->get(route('school.dashboard', $this->schoolA))->assertOk();
});

it('returns 404 for a school slug that does not exist', function () {
    $user = memberOf($this->schoolA);

    $this->actingAs($user)->get('/app/no-such-school')->assertNotFound();
});

it('blocks a disabled membership', function () {
    $user = memberOf($this->schoolA);
    $user->schools()->updateExistingPivot($this->schoolA->id, ['status' => 'disabled']);

    $this->actingAs($user)->get(route('school.dashboard', $this->schoolA))->assertNotFound();
});

it('shows a suspended school as unavailable to its staff but not to platform admins', function () {
    $school = schoolWithRoles(['status' => 'suspended']);
    $staff = memberOf($school);

    $this->actingAs($staff)->get(route('school.dashboard', $school))
        ->assertForbidden()
        ->assertSee('is not available right now');

    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get(route('school.dashboard', $school))
        ->assertOk();
});

it('scopes roles to the school, so a teacher in one school is not a teacher in another', function () {
    $user = memberOf($this->schoolA, SchoolRole::Teacher);
    app(AddSchoolMember::class)->handle($this->schoolB, $user, SchoolRole::Bursar);

    $rolesIn = fn (School $school) => $this->current->run($school, function () use ($user) {
        $user->unsetRelation('roles');

        return $user->getRoleNames()->all();
    });

    expect($rolesIn($this->schoolA))->toBe(['teacher'])
        ->and($rolesIn($this->schoolB))->toBe(['bursar']);
});

it('redirects guests to the login page', function () {
    $this->get(route('school.dashboard', $this->schoolA))->assertRedirect(route('login'));
});

class RecordsCurrentSchoolJob implements ShouldQueue
{
    use Queueable, SchoolAware;

    public static ?int $ranFor = null;

    public function __construct()
    {
        $this->captureSchool();
    }

    public function handle(CurrentSchool $current): void
    {
        self::$ranFor = $current->id();
    }
}

/**
 * An invoice with everything it hangs off, in the school currently in context.
 */
function isolationInvoice(): Invoice
{
    $session = AcademicSession::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-14', 'ends_on' => '2027-07-23']);
    $student = Student::query()->create(['admission_no' => 'ADM/'.fake()->unique()->numerify('####'), 'first_name' => 'Aisha', 'last_name' => 'Bello', 'gender' => 'female']);
    $enrolment = $student->enrolments()->create([
        'academic_session_id' => $session->id,
        'class_arm_id' => ClassLevel::query()->create(['name' => 'JSS 1', 'short_name' => 'JSS 1', 'section' => 'junior'])->arms()->create(['name' => 'A'])->id,
    ]);

    return Invoice::query()->create([
        'invoice_no' => 'INV/26/'.fake()->unique()->numerify('#####'),
        'student_id' => $student->id,
        'enrolment_id' => $enrolment->id,
        'term_id' => $session->terms()->create(['sequence' => 1, 'name' => 'First Term', 'starts_on' => '2026-09-14', 'ends_on' => '2026-12-18'])->id,
        'total' => 150_000_00,
        'status' => 'unpaid',
        'issued_at' => now(),
    ]);
}

function isolationClassSubject(): ClassSubject
{
    return ClassLevel::query()->create(['name' => 'JSS 2', 'short_name' => 'JSS 2', 'section' => 'junior'])->arms()->create(['name' => 'A'])
        ->classSubjects()->create(['subject_id' => Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH'.fake()->unique()->numerify('##'), 'category' => 'core'])->id]);
}

function isolationAssignment(?Invoice $invoice = null): Assignment
{
    return Assignment::query()->create([
        'class_subject_id' => isolationClassSubject()->id, 'term_id' => ($invoice ?? isolationInvoice())->term_id,
        'title' => 'Exercise', 'instructions' => 'Go', 'due_at' => now()->addDay(), 'status' => 'published',
    ]);
}

function isolationQuiz(?Invoice $invoice = null): Quiz
{
    return Quiz::query()->create([
        'class_subject_id' => isolationClassSubject()->id, 'term_id' => ($invoice ?? isolationInvoice())->term_id,
        'title' => 'Quiz', 'kind' => 'quiz', 'status' => 'draft',
    ]);
}

function isolationPayment(Invoice $invoice): Payment
{
    return Payment::query()->create(['student_id' => $invoice->student_id, 'amount' => 1_000_00, 'method' => 'cash', 'status' => 'confirmed', 'paid_at' => now()]);
}
