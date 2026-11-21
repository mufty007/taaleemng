<?php

namespace App\Jobs;

use App\Actions\People\EnrolStudent;
use App\Enums\ImportStatus;
use App\Models\ClassArm;
use App\Models\School;
use App\Models\StudentImport;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\SchoolAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Enrols the checked rows of a student import, a chunk at a time. Each chunk
 * queues the next, so no single job runs long enough to hit a worker timeout,
 * and the import page can show progress between chunks.
 */
class ImportStudents implements ShouldQueue
{
    use Queueable, SchoolAware;

    /**
     * Rows per job. Each row hashes up to two new passwords, which is the slow part.
     */
    public const CHUNK = 25;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $importId, public int $offset = 0)
    {
        $this->captureSchool();
    }

    public function handle(EnrolStudent $enrol): void
    {
        $import = StudentImport::query()->find($this->importId);

        if ($import === null || ! $import->status->isInProgress()) {
            return;
        }

        if ($this->offset === 0) {
            $import->update(['status' => ImportStatus::Running, 'started_at' => now()]);
        }

        $school = app(CurrentSchool::class)->getOrFail();
        $arms = ClassArm::query()->with('level')->get()->keyBy('id');
        $chunk = array_slice($import->rows, $this->offset, self::CHUNK);
        $credentials = $import->credentials ?? [];
        $problems = $import->problems;
        $created = 0;

        foreach ($chunk as $row) {
            $arm = $arms->get($row['class_arm_id']);
            $name = trim($row['student']['first_name'].' '.$row['student']['last_name']);

            try {
                if ($arm === null) {
                    throw ValidationException::withMessages(['class' => 'The class was deleted before the import ran.']);
                }

                $result = $enrol->handle($row['student'], $arm, $row['guardian']);
            } catch (ValidationException $exception) {
                $problems[] = ['line' => $row['line'], 'name' => $name, 'messages' => $exception->validator->errors()->all()];

                continue;
            }

            $created++;
            $credentials[] = [
                'name' => $result->student->fullName(),
                'class' => $arm->displayName(),
                'admission_no' => $result->student->admission_no,
                'student_login' => $school->short_code.' + '.$result->student->admission_no,
                'student_password' => $result->studentPassword,
                'parent' => trim($result->guardian->first_name.' '.$result->guardian->last_name),
                'parent_login' => $result->guardian->phone,
                'parent_password' => $result->guardianPassword,
            ];
        }

        $processed = min($this->offset + self::CHUNK, $import->total_rows);
        $finished = $processed >= $import->total_rows;

        $import->update([
            'processed_rows' => $processed,
            'created_count' => $import->created_count + $created,
            'credentials' => $credentials,
            'problems' => $problems,
            ...($finished ? ['status' => ImportStatus::Completed, 'finished_at' => now(), 'rows' => []] : []),
        ]);

        if ($finished) {
            activity('people')->performedOn($import)->causedBy($import->user_id)
                ->log("imported {$import->created_count} students from {$import->file_name}");

            return;
        }

        self::dispatch($this->importId, $processed);
    }

    /**
     * Mark the import as stopped so the page stops waiting. Runs outside the
     * job middleware, so the school is put back in context here.
     */
    public function failed(?Throwable $exception): void
    {
        $school = School::query()->find($this->schoolId);

        if ($school === null) {
            return;
        }

        app(CurrentSchool::class)->run($school, fn () => StudentImport::query()->whereKey($this->importId)->update([
            'status' => ImportStatus::Failed,
            'finished_at' => now(),
        ]));
    }
}
