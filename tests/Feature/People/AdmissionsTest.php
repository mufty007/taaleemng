<?php

use App\Actions\Academic\StartSession;
use App\Actions\Admissions\SubmitApplication;
use App\Enums\ApplicantStatus;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrolment;
use App\Models\SchoolSetting;
use App\Models\Student;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'short_code' => 'GFA']);
    $this->admin = memberOf($this->school);
    $this->jss1 = inSchool($this->school, fn () => ClassLevel::query()->where('name', 'JSS 1')->sole());

    $this->application = fn (array $overrides = []) => [
        'class_level_id' => $this->jss1->id,
        'first_name' => 'Zainab',
        'last_name' => 'Yusuf',
        'gender' => 'female',
        'date_of_birth' => now()->subYears(11)->toDateString(),
        'guardian_first_name' => 'Halima',
        'guardian_last_name' => 'Yusuf',
        'guardian_relationship' => 'mother',
        'guardian_phone' => '0805 555 1212',
        ...$overrides,
    ];
    $this->applicant = fn (ApplicantStatus $status = ApplicantStatus::Submitted) => inSchool($this->school, function () use ($status) {
        $applicant = app(SubmitApplication::class)->handle(($this->application)(['first_name' => fake()->firstName()]), 'office');
        $applicant->update(['status' => $status]);

        return $applicant;
    });
});

it('shows the school’s public admission form', function () {
    $this->get(route('apply.show', 'greenfield'))
        ->assertOk()
        ->assertSee($this->school->name)
        ->assertSee('Apply for admission')
        ->assertSee('JSS 1');
});

it('takes an application from the public form and gives an application number', function () {
    $this->post(route('apply.store', 'greenfield'), ($this->application)())
        ->assertRedirect(route('apply.thanks', 'greenfield'));

    $this->get(route('apply.thanks', 'greenfield'))->assertOk()->assertSee('APP/'.now()->year.'/0001')->assertSee('+2348055551212');

    inSchool($this->school, function () {
        $applicant = Applicant::query()->sole();

        expect($applicant->status)->toBe(ApplicantStatus::Submitted)
            ->and($applicant->source)->toBe('online')
            ->and($applicant->guardian_phone)->toBe('+2348055551212')
            ->and($applicant->academic_session_id)->toBe(AcademicSession::current()->id);
    });
});

it('checks the public form and quietly drops bot submissions', function () {
    $this->post(route('apply.store', 'greenfield'), ($this->application)(['first_name' => '', 'guardian_phone' => '123']))
        ->assertSessionHasErrors(['first_name', 'guardian_phone']);

    $this->post(route('apply.store', 'greenfield'), ($this->application)(['website' => 'http://spam.test']))
        ->assertRedirect(route('apply.show', 'greenfield'));

    inSchool($this->school, fn () => expect(Applicant::query()->count())->toBe(0));
});

it('admits applicants into the next session once the school has created it', function () {
    $next = inSchool($this->school, fn () => app(StartSession::class)->handle(2027));

    $this->post(route('apply.store', 'greenfield'), ($this->application)());

    inSchool($this->school, fn () => expect(Applicant::query()->sole()->academic_session_id)->toBe($next->id));
});

it('shows a notice when the school has closed its form, and hides suspended schools', function () {
    inSchool($this->school, fn () => SchoolSetting::store('admissions_open', false));
    $this->get(route('apply.show', 'greenfield'))->assertOk()->assertSee('Applications are closed for now');
    $this->post(route('apply.store', 'greenfield'), ($this->application)())->assertNotFound();

    $this->school->update(['status' => SchoolStatus::Suspended]);
    $this->get(route('apply.show', 'greenfield'))->assertNotFound();
    $this->get('/apply/no-such-school')->assertNotFound();
});

it('lists applications by stage for the admissions team only', function () {
    ($this->applicant)();
    $exam = ($this->applicant)(ApplicantStatus::ExamScheduled);

    $this->actingAs($this->admin)->get(route('school.admissions.index', $this->school))
        ->assertOk()
        ->assertSee(route('apply.show', 'greenfield'))
        ->assertDontSee($exam->registerName());
    $this->actingAs($this->admin)->get(route('school.admissions.index', [$this->school, 'stage' => 'exam']))->assertSee($exam->registerName());

    $this->actingAs(staffOf($this->school)->user)->get(route('school.admissions.index', $this->school))->assertForbidden();
    $this->actingAs(memberOf($this->school, SchoolRole::Bursar))->get(route('school.admissions.index', $this->school))->assertForbidden();
});

it('records an application at the office', function () {
    $this->actingAs($this->admin)->post(route('school.admissions.store', $this->school), ($this->application)(['notes' => 'Sibling in JSS 3A']))
        ->assertRedirect();

    inSchool($this->school, fn () => expect(Applicant::query()->sole())
        ->source->toBe('office')
        ->notes->toBe('Sibling in JSS 3A'));
});

it('moves an application through the pipeline, one allowed step at a time', function () {
    $applicant = ($this->applicant)();
    $update = fn (array $data) => $this->actingAs($this->admin)->patch(route('school.admissions.update', [$this->school, $applicant]), $data);

    $update(['status' => 'exam_scheduled'])->assertSessionHasErrors(['exam_at' => 'Choose when the entrance exam is.']);
    $update(['status' => 'accepted'])->assertSessionHasErrors('status');
    $update(['status' => 'exam_scheduled', 'exam_at' => '2026-12-05 09:00'])->assertSessionHasNoErrors();
    $update(['exam_score' => 74.5, 'notes' => 'Strong in maths'])->assertSessionHasNoErrors();

    expect($applicant->fresh())
        ->status->toBe(ApplicantStatus::ExamScheduled)
        ->exam_score->toBe(74.5)
        ->notes->toBe('Strong in maths');
});

it('books an exam for several applications at once', function () {
    $first = ($this->applicant)();
    $second = ($this->applicant)(ApplicantStatus::Screening);
    $enrolled = ($this->applicant)(ApplicantStatus::Enrolled);

    $this->actingAs($this->admin)->post(route('school.admissions.bulk', $this->school), [
        'applicants' => [$first->ulid, $second->ulid, $enrolled->ulid],
        'action' => 'exam_scheduled',
        'exam_at' => '2026-12-05 09:00',
    ])->assertSessionHas('status', '2 applications moved to “Exam booked”. 1 skipped: they can’t move there from where they are.');

    expect($first->fresh()->exam_at->format('Y-m-d H:i'))->toBe('2026-12-05 09:00')
        ->and($second->fresh()->status)->toBe(ApplicantStatus::ExamScheduled)
        ->and($enrolled->fresh()->status)->toBe(ApplicantStatus::Enrolled);
});

it('enrols an offered applicant as a student with a parent login', function () {
    $applicant = ($this->applicant)(ApplicantStatus::Offered);
    $jss1a = inSchool($this->school, fn () => ClassArm::query()->where('class_level_id', $this->jss1->id)->where('name', 'A')->sole());

    $response = $this->actingAs($this->admin)->post(route('school.admissions.enrol', [$this->school, $applicant]), ['class_arm_id' => $jss1a->id]);

    inSchool($this->school, function () use ($applicant, $jss1a, $response) {
        $student = Student::query()->sole();

        $response->assertRedirect(route('school.students.show', $student))->assertSessionHas('credentials');
        expect($student->admission_no)->toBe('GFA/'.now()->year.'/0001')
            ->and($student->first_name)->toBe($applicant->first_name)
            ->and($student->guardians()->sole()->phone)->toBe('+2348055551212')
            ->and(Enrolment::query()->where('student_id', $student->id)->sole()->class_arm_id)->toBe($jss1a->id)
            ->and($applicant->fresh())->status->toBe(ApplicantStatus::Enrolled)->student_id->toBe($student->id);
    });
});

it('only enrols offered applicants, into the class they applied for', function () {
    $new = ($this->applicant)();
    $offered = ($this->applicant)(ApplicantStatus::Offered);
    [$jss1a, $sss1a] = inSchool($this->school, fn () => [
        ClassArm::query()->where('class_level_id', $this->jss1->id)->where('name', 'A')->sole(),
        ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'SSS 1'))->where('name', 'A')->sole(),
    ]);

    $this->actingAs($this->admin)->post(route('school.admissions.enrol', [$this->school, $new]), ['class_arm_id' => $jss1a->id])
        ->assertSessionHasErrors(['class_arm_id' => 'Offer the applicant a place before enrolling them.']);
    $this->actingAs($this->admin)->post(route('school.admissions.enrol', [$this->school, $offered]), ['class_arm_id' => $sss1a->id])
        ->assertSessionHasErrors(['class_arm_id' => 'Choose a class in JSS 1, the class they applied for.']);

    inSchool($this->school, fn () => expect(Student::query()->count())->toBe(0));
});

it('opens and closes the online form', function () {
    $this->actingAs($this->admin)->post(route('school.admissions.form', $this->school))->assertSessionHas('status', 'The online admission form is closed. Families see a notice instead.');
    $this->get(route('apply.show', 'greenfield'))->assertSee('Applications are closed for now');

    $this->actingAs($this->admin)->post(route('school.admissions.form', $this->school));
    $this->get(route('apply.show', 'greenfield'))->assertSee('Apply for admission');
});
