<?php

use App\Actions\Academic\ApplyNigerianDefaults;
use App\Enums\SchoolType;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\Subject;

it('sets up the standard classes and subjects for a combined school', function () {
    $school = schoolWithRoles(['school_type' => SchoolType::Combined, 'slug' => 'greenfield']);
    $admin = memberOf($school);

    $this->actingAs($admin)->post(route('school.classes.defaults', $school))->assertRedirect();

    inSchool($school, function () {
        expect(ClassLevel::query()->orderBy('position')->pluck('name')->all())->toBe([
            'Nursery 1', 'Nursery 2', 'Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6',
            'JSS 1', 'JSS 2', 'JSS 3', 'SSS 1', 'SSS 2', 'SSS 3',
        ]);

        $sss1Science = ClassArm::query()->with('stream')->whereHas('level', fn ($query) => $query->where('name', 'SSS 1'))->where('name', 'A')->sole();
        $sss1Commercial = ClassArm::query()->with('stream')->whereHas('level', fn ($query) => $query->where('name', 'SSS 1'))->where('name', 'C')->sole();
        $subjectsOf = fn (ClassArm $arm) => $arm->classSubjects()->with('subject')->get()->pluck('subject.code');

        expect($sss1Science->stream->name)->toBe('Science')
            ->and($subjectsOf($sss1Science))->toContain('PHY', 'CHM', 'MTH', 'ENG')->not->toContain('ACC')
            ->and($subjectsOf($sss1Commercial))->toContain('ACC', 'COM')->not->toContain('PHY')
            ->and(Subject::query()->where('code', 'ARB')->exists())->toBeFalse();
    });

    $this->actingAs($admin)->post(route('school.classes.defaults', $school))->assertSessionHasErrors('defaults');
});

it('gives Islamiyya schools Arabic and Qur’an but not CRS', function () {
    $school = schoolWithRoles(['school_type' => SchoolType::Islamiyya]);

    inSchool($school, function () {
        app(ApplyNigerianDefaults::class)->handle();

        expect(Subject::query()->pluck('code'))->toContain('ARB', 'QUR', 'IRS')->not->toContain('CRS', 'PHY')
            ->and(ClassLevel::query()->count())->toBe(8);
    });
});

it('adds a class arm with the default subjects for its section', function () {
    $school = schoolWithStructure(['slug' => 'greenfield']);
    $admin = memberOf($school);
    $jss2 = inSchool($school, fn () => ClassLevel::query()->where('name', 'JSS 2')->sole());

    $this->actingAs($admin)
        ->post(route('school.classes.store', $school), ['class_level_id' => $jss2->id, 'name' => 'c'])
        ->assertSessionHasNoErrors();

    inSchool($school, function () use ($jss2) {
        $arm = ClassArm::query()->where('class_level_id', $jss2->id)->where('name', 'C')->sole();

        expect($arm->classSubjects()->count())->toBe(ClassArm::query()->where('class_level_id', $jss2->id)->where('name', 'A')->sole()->classSubjects()->count());
    });

    $this->actingAs($admin)
        ->post(route('school.classes.store', $school), ['class_level_id' => $jss2->id, 'name' => 'C'])
        ->assertSessionHasErrorsIn('addArm', 'name');
});

it('will not accept another school’s class level', function () {
    $school = schoolWithStructure();
    $other = schoolWithStructure();
    $foreignLevel = inSchool($other, fn () => ClassLevel::query()->first());

    $this->actingAs(memberOf($school))
        ->post(route('school.classes.store', $school), ['class_level_id' => $foreignLevel->id, 'name' => 'Z'])
        ->assertSessionHasErrorsIn('addArm', 'class_level_id');
});

it('assigns a form teacher and subject teachers', function () {
    $school = schoolWithStructure();
    $admin = memberOf($school);
    $teacher = staffOf($school);
    $arm = inSchool($school, fn () => ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole());
    $classSubject = inSchool($school, fn () => $arm->classSubjects()->first());

    $this->actingAs($admin)->patch(route('school.classes.update', [$school, $arm]), ['form_teacher_id' => $teacher->id])->assertSessionHasNoErrors();
    $this->actingAs($admin)->patch(route('school.class-subjects.update', [$school, $classSubject]), ['teacher_id' => $teacher->id])->assertSessionHasNoErrors();

    inSchool($school, function () use ($arm, $classSubject, $teacher) {
        expect($arm->fresh()->form_teacher_id)->toBe($teacher->id)
            ->and($classSubject->fresh()->teacher_id)->toBe($teacher->id)
            ->and($teacher->classArmIds())->toBe([$arm->id]);
    });

    $this->actingAs($admin)->delete(route('school.class-subjects.destroy', [$school, $classSubject]))->assertRedirect();
    expect(inSchool($school, fn () => ClassSubject::query()->find($classSubject->id)))->toBeNull();
});

it('lets a teacher open only the classes they teach', function () {
    $school = schoolWithStructure();
    $staff = staffOf($school);
    [$mine, $notMine] = inSchool($school, function () use ($staff) {
        $arms = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->orderBy('name')->get();
        $arms[0]->classSubjects()->first()->update(['teacher_id' => $staff->id]);

        return [$arms[0], ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'SSS 3'))->first()];
    });

    $this->actingAs($staff->user)->get(route('school.classes.show', [$school, $mine]))->assertOk();
    $this->actingAs($staff->user)->get(route('school.classes.show', [$school, $notMine]))->assertNotFound();
    $this->actingAs($staff->user)->get(route('school.subjects.index', $school))->assertForbidden();
});
