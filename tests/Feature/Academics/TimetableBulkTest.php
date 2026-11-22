<?php

use App\Enums\SchoolRole;
use App\Models\ClassArm;
use App\Models\Period;
use App\Models\Subject;
use App\Models\TimetableEntry;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->principal = memberOf($this->school, SchoolRole::Principal);
    $this->teacher = staffOf($this->school, SchoolRole::Teacher, ['first_name' => 'Emeka', 'last_name' => 'Okonkwo']);
    enrol($this->school, 'JSS 1', 'A');
    enrol($this->school, 'JSS 2', 'A');

    $this->actingAs($this->principal)->post(route('school.timetable.periods.defaults', $this->school));

    inSchool($this->school, function () {
        $this->jss1a = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->jss2a = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 2'))->where('name', 'A')->sole();
        $maths = Subject::query()->where('code', 'MTH')->value('id');
        $this->jss1a->classSubjects()->where('subject_id', $maths)->sole()->update(['teacher_id' => $this->teacher->id]);
        $this->jss2a->classSubjects()->where('subject_id', $maths)->sole()->update(['teacher_id' => $this->teacher->id]);
    });
});

it('fills empty lessons for every class at once', function () {
    $this->actingAs($this->principal)->post(route('school.timetable.autofill-all', $this->school))
        ->assertRedirect(route('school.timetable.index', $this->school));

    inSchool($this->school, function () {
        expect(TimetableEntry::query()->count())->toBeGreaterThan(40)
            ->and(Period::query()->where('name', 'Short break')->exists())->toBeTrue()
            ->and(TimetableEntry::query()->where('period_id', Period::query()->where('name', 'Short break')->value('id'))->exists())->toBeFalse();
    });
});

it('rebuilds every class from scratch, replacing lessons placed by hand', function () {
    $this->actingAs($this->principal)->post(route('school.timetable.autofill-all', $this->school));
    $handPlaced = inSchool($this->school, fn () => TimetableEntry::query()->where('class_arm_id', $this->jss1a->id)->orderBy('id')->value('id'));

    $this->actingAs($this->principal)->post(route('school.timetable.autofill-all', $this->school))
        ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Nothing to place'));

    $this->actingAs($this->principal)->post(route('school.timetable.rebuild-all', $this->school))
        ->assertRedirect(route('school.timetable.index', $this->school))
        ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Rebuilt the timetable'));

    inSchool($this->school, function () use ($handPlaced) {
        expect(TimetableEntry::query()->whereKey($handPlaced)->exists())->toBeFalse()
            ->and(TimetableEntry::query()->count())->toBeGreaterThan(40);
    });
});

it('keeps the whole-school rebuild away from teachers', function () {
    $this->actingAs($this->teacher->user)->post(route('school.timetable.rebuild-all', $this->school))->assertForbidden();
});
