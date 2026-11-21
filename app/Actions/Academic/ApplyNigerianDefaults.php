<?php

namespace App\Actions\Academic;

use App\Actions\Results\SetUpAssessment;
use App\Enums\ClassSection;
use App\Models\ClassLevel;
use App\Models\Stream;
use App\Models\Subject;
use App\Support\Academic\NigerianCurriculum;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One click for a new school: create the class levels, an "A" arm for each
 * (one arm per stream in SSS), the subject list, and each class's subjects.
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

        DB::transaction(function () use ($school): void {
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
                            $this->addClassArm->handle($level, chr(65 + $index), $stream);
                        }
                    } else {
                        $this->addClassArm->handle($level, 'A');
                    }
                }
            }

            $this->setUpAssessment->handle();
        });

        activity('academics')->log('set up the default Nigerian classes and subjects');
    }
}
