<?php

namespace App\Actions\Academic;

use App\Actions\Results\SetUpAssessment;
use App\Enums\ClassSection;
use App\Enums\ClassStructure;
use App\Models\ClassLevel;
use App\Models\Stream;
use App\Models\Subject;
use App\Support\Academic\NigerianCurriculum;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One click for a new school: create the class levels, arms (or a single class
 * per level when the school uses that structure), subjects, and class subjects.
 */
class ApplyNigerianDefaults
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private AddClassArm $addClassArm,
        private SetUpAssessment $setUpAssessment,
    ) {}

    public function handle(): void
    {
        $school = $this->currentSchool->getOrFail();

        if (ClassLevel::query()->exists()) {
            throw ValidationException::withMessages(['defaults' => 'This school already has classes set up.']);
        }

        $single = $school->class_structure === ClassStructure::Single;

        DB::transaction(function () use ($school, $single): void {
            foreach (NigerianCurriculum::subjectsFor($school->school_type) as $subject) {
                Subject::query()->firstOrCreate(['code' => $subject['code']], [
                    'name' => $subject['name'],
                    'category' => $subject['category'],
                ]);
            }

            $streams = [];

            foreach (NigerianCurriculum::sectionsFor($school->school_type) as $section) {
                if ($section === ClassSection::Senior) {
                    foreach (NigerianCurriculum::STREAMS as $streamName) {
                        $streams[$streamName] = Stream::query()->firstOrCreate(['name' => $streamName]);
                    }
                }

                foreach (NigerianCurriculum::levels($section) as $levelData) {
                    $level = ClassLevel::query()->create($levelData);

                    if ($section === ClassSection::Senior) {
                        foreach (array_values($streams) as $index => $stream) {
                            // Single structure: use the stream name so arms stay unique per level.
                            $this->addClassArm->handle($level, $single ? $stream->name : chr(65 + $index), $stream);
                        }
                    } else {
                        $this->addClassArm->handle($level, $single ? '' : 'A');
                    }
                }
            }

            $this->setUpAssessment->handle();
        });

        activity('academics')->log('set up the default Nigerian classes and subjects');
    }
}
