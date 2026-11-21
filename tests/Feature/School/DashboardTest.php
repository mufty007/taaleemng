<?php

use App\Enums\ClassworkStatus;
use App\Enums\SchoolRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassArm;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

it('greets the user and shows the school admin a setup checklist', function () {
    $school = schoolWithRoles(['name' => 'Greenfield Academy']);
    $admin = memberOf($school, SchoolRole::SchoolAdmin, ['name' => 'Ngozi Okafor']);
    $principal = memberOf($school, SchoolRole::Principal, ['name' => 'Funke Adebayo']);

    $this->actingAs($admin)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Ngozi')
        ->assertSee('Get your school ready');

    $this->actingAs($principal)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Principal / Head Teacher')
        ->assertDontSee('Get your school ready');
});

it('shows a teacher their own classes and numbers', function () {
    $school = schoolWithStructure();
    $teacher = staffOf($school);
    enrol($school, 'JSS 1', 'A');
    enrol($school, 'SSS 2', 'A');

    inSchool($school, fn () => ClassArm::query()
        ->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole()
        ->update(['form_teacher_id' => $teacher->id]));

    $this->actingAs($teacher->user)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('My classes')
        ->assertSee('My students')
        ->assertSee('Form teacher');
});

it('only shows sidebar modules the role is allowed to use', function () {
    $school = schoolWithRoles();

    $bursar = memberOf($school, SchoolRole::Bursar);
    $teacher = memberOf($school, SchoolRole::Teacher);

    $this->actingAs($bursar)->get(route('school.dashboard', $school))
        ->assertSee('Debtors')
        ->assertDontSee('Score entry');

    $this->actingAs($teacher)->get(route('school.dashboard', $school))
        ->assertSee('Score entry')
        ->assertDontSee('Payroll');
});

it('records sign-ins in the activity log with the client IP', function () {
    $school = schoolWithRoles();
    $user = memberOf($school);

    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password']);

    $activity = Activity::query()->where('description', 'logged in')->sole();

    expect($activity->causer_id)->toBe($user->id)
        ->and($activity->ip_address)->toBe('127.0.0.1');
});

it('puts what needs doing first for each role', function () {
    $school = schoolWithStructure();
    $teacher = staffOf($school);
    $aisha = enrol($school, 'JSS 1', 'A', ['first_name' => 'Aisha'])->student;

    $assignment = inSchool($school, function () use ($teacher, $aisha) {
        $arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $arm->update(['form_teacher_id' => $teacher->id]);
        $maths = $arm->classSubjects()->first();
        $maths->update(['teacher_id' => $teacher->id]);
        $assignment = Assignment::query()->create([
            'class_subject_id' => $maths->id, 'term_id' => Term::current()->id, 'title' => 'Long division',
            'instructions' => 'Go', 'due_at' => now()->addDay(), 'status' => ClassworkStatus::Published,
        ]);
        AssignmentSubmission::query()->create(['assignment_id' => $assignment->id, 'student_id' => $aisha->id, 'body' => 'Done', 'submitted_at' => now()]);

        return $assignment;
    });

    $this->travelTo(inSchool($school, fn () => Term::current()->starts_on->copy()->next(Carbon::TUESDAY)->setTime(10, 0)));

    $this->actingAs($teacher->user)->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Needs your attention')
        ->assertSee('Take today’s register for JSS 1A')
        ->assertSee('1 hand-in to mark')
        ->assertSee('Long division');

    $this->actingAs(memberOf($school))->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('haven’t taken today’s register')
        ->assertSee('Today’s registers')
        ->assertSee('School fees this term');

    $this->actingAs(memberOf($school, SchoolRole::Bursar))->get(route('school.dashboard', $school))
        ->assertOk()
        ->assertSee('Collected today')
        ->assertSee('Quick actions')
        ->assertDontSee('Today’s registers');
});
