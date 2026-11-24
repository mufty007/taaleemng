<?php

use App\Actions\Academic\AddClassArm;
use App\Actions\Academic\MergeClassArms;
use App\Enums\ClassStructure;
use App\Enums\SchoolRole;
use App\Models\Announcement;
use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Enrolment;
use App\Models\Period;
use App\Models\ResultPublication;
use App\Models\Score;
use App\Models\Term;
use App\Models\TimetableEntry;

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

it('keeps everything teachers recorded when arms merge', function () {
    $school = schoolWithStructure();
    $admin = memberOf($school, SchoolRole::SchoolAdmin);

    $setup = inSchool($school, function () use ($school, $admin) {
        $level = ClassLevel::query()->where('name', 'JSS 1')->sole();
        $armA = $level->arms()->where('name', 'A')->sole();
        $armA->update(['capacity' => 30]);
        $armB = app(AddClassArm::class)->handle($level, 'B');
        $armB->update(['capacity' => 25]);
        $studentB = enrol($school, 'JSS 1', 'B');

        $term = Term::current();
        $period = Period::query()->create(['name' => 'Period 1', 'starts_at' => '08:00', 'ends_at' => '08:40', 'position' => 1]);
        $secondPeriod = Period::query()->create(['name' => 'Period 2', 'starts_at' => '08:40', 'ends_at' => '09:20', 'position' => 2]);
        $mathsA = $armA->classSubjects()->whereHas('subject', fn ($query) => $query->where('code', 'MTH'))->sole();
        $mathsB = $armB->classSubjects()->whereHas('subject', fn ($query) => $query->where('code', 'MTH'))->sole();

        $score = Score::query()->create(['term_id' => $term->id, 'enrolment_id' => $studentB->enrolment->id, 'class_subject_id' => $mathsB->id, 'assessment_component_id' => AssessmentComponent::query()->value('id'), 'score' => 17, 'entered_by' => $admin->id]);
        $notice = Announcement::query()->create(['title' => 'JSS 1B trip', 'body' => 'Bring lunch.', 'audience' => 'class', 'class_arm_id' => $armB->id, 'published_at' => now(), 'created_by' => $admin->id]);
        ResultPublication::query()->create(['term_id' => $term->id, 'class_arm_id' => $armB->id, 'published_by' => $admin->id, 'published_at' => now()]);
        TimetableEntry::query()->create(['class_arm_id' => $armA->id, 'period_id' => $period->id, 'day' => 1, 'class_subject_id' => $mathsA->id]);
        $clash = TimetableEntry::query()->create(['class_arm_id' => $armB->id, 'period_id' => $period->id, 'day' => 1, 'class_subject_id' => $mathsB->id]);
        $free = TimetableEntry::query()->create(['class_arm_id' => $armB->id, 'period_id' => $secondPeriod->id, 'day' => 1, 'class_subject_id' => $mathsB->id]);

        app(MergeClassArms::class)->handle($level);

        return compact('armA', 'mathsA', 'score', 'notice', 'clash', 'free', 'term');
    });

    inSchool($school, function () use ($setup) {
        ['armA' => $armA, 'mathsA' => $mathsA] = $setup;

        expect($setup['score']->fresh()->class_subject_id)->toBe($mathsA->id)
            ->and($setup['notice']->fresh()->class_arm_id)->toBe($armA->id)
            ->and(ResultPublication::query()->where('term_id', $setup['term']->id)->where('class_arm_id', $armA->id)->exists())->toBeTrue()
            ->and(TimetableEntry::query()->find($setup['clash']->id))->toBeNull()
            ->and($setup['free']->fresh()->only(['class_arm_id', 'class_subject_id']))->toBe(['class_arm_id' => $armA->id, 'class_subject_id' => $mathsA->id])
            ->and($armA->fresh()->capacity)->toBe(55);
    });
});

it('gives single classes their letters back when the school returns to A, B, C arms', function () {
    $school = schoolWithStructure(['class_structure' => ClassStructure::Single]);
    $admin = memberOf($school, SchoolRole::SchoolAdmin);

    $this->actingAs($admin)->put(route('school.settings.update', $school), [
        'promotion_pass_mark' => 40,
        'class_structure' => 'arms',
        'numbering' => config('taaleem.numbering'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    inSchool($school, function () {
        $jss1 = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->sole();
        $sss1 = ClassArm::query()->with('stream')->whereHas('level', fn ($query) => $query->where('name', 'SSS 1'))->orderBy('id')->get();

        expect($jss1->name)->toBe('A')
            ->and($jss1->load('level')->displayName())->toBe('JSS 1A')
            ->and($sss1->pluck('name')->all())->toBe(['A', 'B', 'C'])
            ->and($sss1->pluck('stream.name')->filter()->count())->toBe(3);
    });
});

it('adds a level’s one class without a letter, and refuses a second, in single-class mode', function () {
    $school = schoolWithStructure(['class_structure' => ClassStructure::Single]);
    $admin = memberOf($school, SchoolRole::SchoolAdmin);
    $level = inSchool($school, function () {
        $level = ClassLevel::query()->where('name', 'JSS 3')->sole();
        $level->arms()->each(fn (ClassArm $arm) => $arm->delete());

        return $level;
    });

    $this->actingAs($admin)->post(route('school.classes.store', $school), ['class_level_id' => $level->id])
        ->assertSessionHasNoErrors();
    inSchool($school, fn () => expect($level->arms()->sole()->name)->toBe(''));

    $this->actingAs($admin)->post(route('school.classes.store', $school), ['class_level_id' => $level->id])
        ->assertSessionHasErrorsIn('addArm', ['class_level_id']);
    inSchool($school, fn () => expect($level->arms()->count())->toBe(1));

    $this->actingAs($admin)->get(route('school.classes.index', $school))->assertOk()->assertSee('Add a class')->assertDontSee('Arm name');
});
