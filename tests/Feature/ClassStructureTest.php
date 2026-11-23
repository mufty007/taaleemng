<?php

use App\Actions\Academic\AddClassArm;
use App\Actions\Academic\MergeClassArms;
use App\Enums\ClassStructure;
use App\Enums\SchoolRole;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrolment;

it('merges A/B arms into a single class when the school switches structure', function () {
    $school = schoolWithStructure(['slug' => 'merge-me', 'class_structure' => ClassStructure::Arms]);
    $admin = memberOf($school, SchoolRole::SchoolAdmin);

    [$level, $armA, $armB, $studentA, $studentB] = inSchool($school, function () use ($school) {
        $level = ClassLevel::query()->where('name', 'JSS 1')->sole();
        $armA = $level->arms()->where('name', 'A')->sole();
        $armB = app(AddClassArm::class)->handle($level, 'B');
        $studentA = enrol($school, 'JSS 1', 'A')->student;
        $studentB = enrol($school, 'JSS 1', 'B')->student;

        return [$level, $armA, $armB, $studentA, $studentB];
    });

    $this->actingAs($admin)->put(route('school.settings.update', $school), [
        'promotion_pass_mark' => 40,
        'class_structure' => 'single',
        'merge_arms' => '1',
        'admissions_open' => '1',
        'show_positions' => '1',
        'numbering' => config('taaleem.numbering'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($school->fresh()->class_structure)->toBe(ClassStructure::Single);

    inSchool($school, function () use ($level, $armA, $armB, $studentA, $studentB) {
        expect(ClassArm::query()->where('class_level_id', $level->id)->count())->toBe(1)
            ->and(ClassArm::query()->find($armB->id))->toBeNull()
            ->and(Enrolment::query()->where('student_id', $studentA->id)->value('class_arm_id'))->toBe($armA->id)
            ->and(Enrolment::query()->where('student_id', $studentB->id)->value('class_arm_id'))->toBe($armA->id)
            ->and($armA->fresh()->name)->toBe('')
            ->and($armA->fresh()->displayName())->toBe('JSS 1');

        $sssArms = ClassArm::query()->with(['level', 'stream'])
            ->whereHas('level', fn ($query) => $query->where('name', 'SSS 1'))
            ->get();
        expect($sssArms->pluck('name')->sort()->values()->all())->toBe(['Arts', 'Commercial', 'Science'])
            ->and($sssArms->every(fn (ClassArm $arm) => $arm->displayName() === 'SSS 1 '.$arm->stream->name))->toBeTrue();
    });
});

it('merges through the MergeClassArms action directly', function () {
    $school = schoolWithStructure();

    inSchool($school, function () {
        $level = ClassLevel::query()->where('name', 'JSS 2')->sole();
        app(AddClassArm::class)->handle($level, 'B');
        expect($level->arms()->count())->toBe(2);

        $kept = app(MergeClassArms::class)->handle($level);
        expect($level->arms()->count())->toBe(1)->and($kept->first()->name)->toBe('');
    });
});
