<?php

use App\Actions\Academic\StartSession;
use App\Enums\EnrolmentStatus;
use App\Enums\PromotionDecision;
use App\Enums\SchoolRole;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Term;
use App\Models\TermReport;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->admin = memberOf($this->school);

    $this->ada = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Ada', 'last_name' => 'Obi'])->student;
    $this->bayo = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Bayo', 'last_name' => 'Ade'])->student;
    $this->senior = enrol($this->school, 'SSS 3', 'A', ['first_name' => 'Musa', 'last_name' => 'Garba'])->student;

    inSchool($this->school, function () {
        $this->from = AcademicSession::current();
        $this->arm = fn (string $level, string $name) => ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', $level))->where('name', $name)->sole();
        $this->jss1a = ($this->arm)('JSS 1', 'A');
        $this->jss2a = ($this->arm)('JSS 2', 'A');
        $this->sss3a = ($this->arm)('SSS 3', 'A');
    });

    $this->enrolmentOf = fn ($student) => inSchool($this->school, fn () => Enrolment::query()->where('student_id', $student->id)->where('academic_session_id', $this->from->id)->sole());
    $this->startNextSession = fn () => inSchool($this->school, fn () => $this->to = app(StartSession::class)->handle(2027));
});

it('asks for the next session before anyone can be moved', function () {
    $this->actingAs($this->admin)->get(route('school.promotion.index', $this->school))
        ->assertOk()
        ->assertSee('Start the 2027/2028 session first');

    $this->actingAs($this->admin)->get(route('school.promotion.show', [$this->school, $this->jss1a]))->assertNotFound();
});

it('lists each class with how far it has got', function () {
    ($this->startNextSession)();

    $this->actingAs($this->admin)->get(route('school.promotion.index', $this->school))
        ->assertOk()
        ->assertSee('JSS 1A')
        ->assertSee('SSS 3A')
        ->assertSee('Not yet');
});

it('pre-fills the principal’s decision and the class above', function () {
    ($this->startNextSession)();
    inSchool($this->school, fn () => TermReport::query()->create([
        'term_id' => Term::query()->where('academic_session_id', $this->from->id)->where('sequence', 3)->value('id'),
        'enrolment_id' => ($this->enrolmentOf)($this->bayo)->id,
        'promotion_decision' => PromotionDecision::Repeat,
    ]));

    $this->actingAs($this->admin)->get(route('school.promotion.show', [$this->school, $this->jss1a]))
        ->assertOk()
        ->assertSee('OBI Ada')
        ->assertSee('Promoted students go to JSS 2A', escape: false)
        ->assertSee('"repeat"', escape: false);
});

it('promotes, holds back and graduates students into the next session', function () {
    ($this->startNextSession)();
    $ada = ($this->enrolmentOf)($this->ada);
    $bayo = ($this->enrolmentOf)($this->bayo);
    $musa = ($this->enrolmentOf)($this->senior);

    $this->actingAs($this->admin)->post(route('school.promotion.store', [$this->school, $this->jss1a]), ['students' => [
        $ada->id => ['decision' => 'promoted', 'class_arm_id' => $this->jss2a->id],
        $bayo->id => ['decision' => 'repeat', 'class_arm_id' => $this->jss1a->id],
    ]])->assertRedirect(route('school.promotion.index', [$this->school, 'from' => $this->from->id]))
        ->assertSessionHas('status', 'JSS 1A moved into 2027/2028: 1 up, 1 repeating, 0 graduated.');

    $this->actingAs($this->admin)->post(route('school.promotion.store', [$this->school, $this->sss3a]), ['students' => [
        $musa->id => ['decision' => 'graduated'],
    ]])->assertSessionHasNoErrors();

    inSchool($this->school, function () use ($ada, $bayo, $musa) {
        $next = fn ($student) => Enrolment::query()->where('student_id', $student->id)->where('academic_session_id', $this->to->id)->first();

        expect($next($this->ada)->class_arm_id)->toBe($this->jss2a->id)
            ->and($next($this->bayo)->class_arm_id)->toBe($this->jss1a->id)
            ->and($next($this->senior))->toBeNull()
            ->and($ada->fresh()->status)->toBe(EnrolmentStatus::Promoted)
            ->and($bayo->fresh()->status)->toBe(EnrolmentStatus::Repeated)
            ->and($musa->fresh()->status)->toBe(EnrolmentStatus::Graduated)
            ->and($this->senior->fresh()->status)->toBe(StudentStatus::Graduated)
            ->and(TermReport::query()->where('enrolment_id', $bayo->id)->sole()->promotion_decision)->toBe(PromotionDecision::Repeat);
    });
});

it('moves a student again instead of enrolling them twice', function () {
    ($this->startNextSession)();
    $ada = ($this->enrolmentOf)($this->ada);
    $promote = fn (string $decision, ClassArm $arm) => $this->actingAs($this->admin)
        ->post(route('school.promotion.store', [$this->school, $this->jss1a]), ['students' => [$ada->id => ['decision' => $decision, 'class_arm_id' => $arm->id]]]);

    $promote('promoted', $this->jss2a);
    $promote('repeat', $this->jss1a);

    inSchool($this->school, function () use ($ada) {
        expect(Enrolment::query()->where('student_id', $this->ada->id)->where('academic_session_id', $this->to->id)->sole()->class_arm_id)->toBe($this->jss1a->id)
            ->and($ada->fresh()->status)->toBe(EnrolmentStatus::Repeated);
    });
});

it('needs a class for everyone who is not graduating, and ignores students from other classes', function () {
    ($this->startNextSession)();
    $ada = ($this->enrolmentOf)($this->ada);
    $musa = ($this->enrolmentOf)($this->senior);

    $this->actingAs($this->admin)->post(route('school.promotion.store', [$this->school, $this->jss1a]), ['students' => [
        $ada->id => ['decision' => 'promoted', 'class_arm_id' => null],
    ]])->assertSessionHasErrors(["students.{$ada->id}.class_arm_id" => 'Choose the class each student moves into.']);

    $this->actingAs($this->admin)->post(route('school.promotion.store', [$this->school, $this->jss1a]), ['students' => [
        $musa->id => ['decision' => 'promoted', 'class_arm_id' => $this->jss2a->id],
    ]])->assertSessionHas('status', 'JSS 1A moved into 2027/2028: 0 up, 0 repeating, 0 graduated.');

    inSchool($this->school, fn () => expect(Enrolment::query()->where('academic_session_id', $this->to->id)->count())->toBe(0));
});

it('keeps teachers and the bursar out', function () {
    ($this->startNextSession)();

    $this->actingAs(staffOf($this->school)->user)->get(route('school.promotion.index', $this->school))->assertForbidden();
    $this->actingAs(memberOf($this->school, SchoolRole::Bursar))->post(route('school.promotion.store', [$this->school, $this->jss1a]), ['students' => []])->assertForbidden();
});
