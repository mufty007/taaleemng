<?php

use App\Enums\SchoolRole;
use App\Models\Announcement;
use App\Models\ClassArm;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->principal = memberOf($this->school, SchoolRole::Principal);
    $this->teacher = staffOf($this->school);
    $this->jss1 = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha']);
    $this->jss2 = enrol($this->school, 'JSS 2', 'A', ['first_name' => 'Chidi']);

    inSchool($this->school, function () {
        $this->jss1a = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->jss2a = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 2'))->where('name', 'A')->sole();
        $this->jss1a->update(['form_teacher_id' => $this->teacher->id]);
    });

    $this->post = fn ($user, array $data) => $this->actingAs($user)->post(route('school.announcements.store', $this->school), [
        'title' => 'PTA meeting', 'body' => 'Saturday at 10am in the hall.', ...$data,
    ]);
});

it('shows each notice only to the people it is meant for', function () {
    ($this->post)($this->principal, ['title' => 'Mid-term break', 'audience' => 'everyone'])->assertSessionHasNoErrors();
    ($this->post)($this->principal, ['title' => 'Staff briefing', 'audience' => 'staff']);
    ($this->post)($this->principal, ['title' => 'Parents evening', 'audience' => 'parents']);
    ($this->post)($this->principal, ['title' => 'JSS 1A excursion', 'audience' => 'class', 'class_arm_id' => $this->jss1a->id]);

    $this->actingAs($this->jss1->guardian->user)->get(route('school.announcements.index', $this->school))
        ->assertOk()->assertSee('Mid-term break')->assertSee('Parents evening')->assertSee('JSS 1A excursion')->assertDontSee('Staff briefing');
    $this->actingAs($this->jss2->student->user)->get(route('school.announcements.index', $this->school))
        ->assertSee('Mid-term break')->assertDontSee('Parents evening')->assertDontSee('JSS 1A excursion');
    $this->actingAs($this->teacher->user)->get(route('school.announcements.index', $this->school))
        ->assertSee('Staff briefing')->assertSee('JSS 1A excursion')->assertDontSee('Parents evening');

    $staffOnly = inSchool($this->school, fn () => Announcement::query()->where('title', 'Staff briefing')->sole());
    $this->actingAs($this->jss1->guardian->user)->get(route('school.announcements.show', [$this->school, $staffOnly]))->assertNotFound();
});

it('counts unread notices on the bell until they are opened', function () {
    ($this->post)($this->principal, ['title' => 'Sports day', 'audience' => 'everyone']);
    ($this->post)($this->principal, ['title' => 'Exam timetable out', 'audience' => 'students']);
    $parent = $this->jss1->guardian->user;
    $student = $this->jss1->student->user;

    $this->actingAs($student)->get(route('school.portal', $this->school))->assertSee('Notices, 2 unread')->assertSee('Sports day');

    $notice = inSchool($this->school, fn () => Announcement::query()->where('title', 'Sports day')->sole());
    $this->actingAs($student)->get(route('school.announcements.show', [$this->school, $notice]))->assertOk()->assertSee('Sports day');
    $this->actingAs($student)->get(route('school.portal', $this->school))->assertSee('Notices, 1 unread');

    $this->actingAs($parent)->get(route('school.portal', $this->school))->assertSee('Notices, 1 unread');
    $this->actingAs($parent)->post(route('school.announcements.read', $this->school))->assertSessionHas('status');
    $this->actingAs($parent)->get(route('school.portal', $this->school))->assertDontSee('unread');
});

it('lets teachers post only to the classes they teach', function () {
    ($this->post)($this->teacher->user, ['audience' => 'class', 'class_arm_id' => $this->jss1a->id])->assertSessionHasNoErrors();
    ($this->post)($this->teacher->user, ['audience' => 'class', 'class_arm_id' => $this->jss2a->id])->assertSessionHasErrorsIn('announcement', 'class_arm_id');
    ($this->post)($this->teacher->user, ['audience' => 'everyone'])->assertSessionHasErrorsIn('announcement', 'audience');
    ($this->post)($this->jss1->guardian->user, ['audience' => 'everyone'])->assertForbidden();

    $notice = inSchool($this->school, fn () => Announcement::query()->sole());
    $this->actingAs(staffOf($this->school)->user)->delete(route('school.announcements.destroy', [$this->school, $notice]))->assertForbidden();
    $this->actingAs($this->teacher->user)->delete(route('school.announcements.destroy', [$this->school, $notice]))->assertRedirect();
});

it('hides notices once they expire', function () {
    ($this->post)($this->principal, ['title' => 'Uniform sale', 'audience' => 'everyone', 'expires_at' => now()->addDays(2)->toDateString()]);

    $this->actingAs($this->jss1->student->user)->get(route('school.announcements.index', $this->school))->assertSee('Uniform sale');
    $this->travel(3)->days();
    $this->actingAs($this->jss1->student->user)->get(route('school.announcements.index', $this->school))->assertDontSee('Uniform sale');
});
