<?php

namespace App\Actions\Academic;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Stream;
use App\Models\Subject;
use App\Support\Academic\NigerianCurriculum;
use Illuminate\Support\Facades\DB;

/**
 * Create a class arm (e.g. JSS 2B) and give it the default subjects for its section and stream.
 */
class AddClassArm
{
    public function handle(ClassLevel $level, string $name, ?Stream $stream = null, ?int $formTeacherId = null): ClassArm
    {
        return DB::transaction(function () use ($level, $name, $stream, $formTeacherId): ClassArm {
            $arm = $level->arms()->create([
                'name' => $name,
                'stream_id' => $stream?->id,
                'form_teacher_id' => $formTeacherId,
            ]);

            $codes = collect(NigerianCurriculum::subjects())
                ->filter(fn (array $subject): bool => NigerianCurriculum::appliesTo($subject, $level->section, $stream?->name))
                ->pluck('code');

            Subject::query()->whereIn('code', $codes)->each(function (Subject $subject) use ($arm): void {
                $arm->classSubjects()->create([
                    'subject_id' => $subject->id,
                    'is_compulsory' => ! in_array($subject->code, ['CRS', 'IRS', 'FRE'], true),
                ]);
            });

            return $arm;
        });
    }
}
