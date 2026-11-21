<?php

use App\Models\AssessmentComponent;
use App\Models\ClassArm;
use App\Models\Score;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Results\ResultCalculator;

beforeEach(function () {
    $this->school = schoolWithStructure();

    $this->students = collect(['Adamu', 'Bello', 'Chukwu', 'Danjuma'])
        ->mapWithKeys(fn (string $surname) => [$surname => enrol($this->school, 'JSS 1', 'A', ['last_name' => $surname])->student]);

    inSchool($this->school, function () {
        $this->arm = ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole();
        $this->term = Term::current();
        $this->components = AssessmentComponent::query()->orderBy('position')->pluck('id')->all();
        $this->maths = $this->arm->classSubjects()->where('subject_id', Subject::query()->where('code', 'MTH')->value('id'))->sole();
        $this->english = $this->arm->classSubjects()->where('subject_id', Subject::query()->where('code', 'ENG')->value('id'))->sole();

        $enter = function (string $surname, $classSubject, array $scores): void {
            foreach ($scores as $index => $score) {
                Score::query()->create([
                    'term_id' => $this->term->id,
                    'enrolment_id' => $this->students[$surname]->currentEnrolment->id,
                    'class_subject_id' => $classSubject->id,
                    'assessment_component_id' => $this->components[$index],
                    'score' => $score,
                ]);
            }
        };

        // Maths: Adamu 90, Bello 70, Chukwu 70. English: Bello 100, Chukwu 100, Adamu 50. Danjuma has nothing yet.
        $enter('Adamu', $this->maths, [20, 20, 50]);
        $enter('Bello', $this->maths, [15, 15, 40]);
        $enter('Chukwu', $this->maths, [15, 15, 40]);
        $enter('Adamu', $this->english, [10, 10, 30]);
        $enter('Bello', $this->english, [20, 20, 60]);
        $enter('Chukwu', $this->english, [20, 20, 60]);
    });

    $this->results = inSchool($this->school, fn () => app(ResultCalculator::class)->forClass($this->arm, $this->term));
    $this->row = fn (string $surname) => $this->results->forStudent($this->students[$surname]->id);
});

it('totals each subject and grades it', function () {
    $adamu = ($this->row)('Adamu');

    expect($adamu['subjects'][$this->maths->id])->toMatchArray(['total' => 90.0, 'grade' => 'A', 'complete' => true])
        ->and($adamu['subjects'][$this->english->id])->toMatchArray(['total' => 50.0, 'grade' => 'C', 'remark' => 'Good'])
        ->and($adamu['total'])->toBe(140.0)
        ->and($adamu['average'])->toBe(70.0)
        ->and($adamu['subject_count'])->toBe(2);
});

it('lets equal subject totals share a position and skips the next', function () {
    expect(($this->row)('Adamu')['subjects'][$this->maths->id]['position'])->toBe(1)
        ->and(($this->row)('Bello')['subjects'][$this->maths->id]['position'])->toBe(2)
        ->and(($this->row)('Chukwu')['subjects'][$this->maths->id]['position'])->toBe(2)
        ->and(($this->row)('Bello')['subjects'][$this->english->id]['position'])->toBe(1)
        ->and(($this->row)('Chukwu')['subjects'][$this->english->id]['position'])->toBe(1)
        ->and(($this->row)('Adamu')['subjects'][$this->english->id]['position'])->toBe(3);
});

it('ranks the class by average with ties', function () {
    expect(($this->row)('Bello')['position'])->toBe(1)
        ->and(($this->row)('Chukwu')['position'])->toBe(1)
        ->and(($this->row)('Adamu')['position'])->toBe(3);
});

it('leaves a student with no scores unranked', function () {
    $danjuma = ($this->row)('Danjuma');

    expect($danjuma['average'])->toBeNull()
        ->and($danjuma)->not->toHaveKey('position')
        ->and($this->results->classSize)->toBe(4);
});

it('works out subject statistics and how much has been entered', function () {
    expect($this->results->statistics[$this->maths->id])->toBe(['average' => 76.7, 'highest' => 90.0, 'lowest' => 70.0, 'count' => 3])
        ->and($this->results->enteredEntries)->toBe(18)
        ->and($this->results->expectedEntries)->toBe(4 * $this->results->classSubjects->count() * 3)
        ->and($this->results->completion())->toBe((int) round(18 / $this->results->expectedEntries * 100));
});
