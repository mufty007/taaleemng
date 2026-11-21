<?php

namespace App\Http\Controllers\School;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportStudents;
use App\Models\ClassArm;
use App\Models\StudentImport;
use App\Support\People\StudentListReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk student import: upload a CSV, see every problem before anything is
 * saved, then enrol the good rows on the queue and hand out the new logins.
 */
class StudentImportController extends Controller
{
    public function create(): View
    {
        return view('school.students.import.create', [
            'imports' => StudentImport::query()->with('user')->latest()->limit(10)->get(),
            'columns' => StudentListReader::COLUMNS,
            'required' => StudentListReader::REQUIRED,
            'classExamples' => ClassArm::query()->with('level')->get()
                ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
                ->map(fn (ClassArm $arm) => $arm->displayName())
                ->values(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "\xEF\xBB\xBF".StudentListReader::template();
        }, 'taaleem-student-list.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, StudentListReader $reader): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ], [
            'file.mimes' => 'Upload a CSV file. In Excel, use File › Save As › CSV (Comma delimited).',
        ]);

        $file = $request->file('file');
        ['rows' => $rows, 'problems' => $problems] = $reader->read($file->getRealPath());

        $import = StudentImport::query()->create([
            'user_id' => $request->user()->id,
            'file_name' => Str::limit($file->getClientOriginalName(), 150),
            'status' => ImportStatus::Checked,
            'rows' => $rows,
            'problems' => $problems,
            'total_rows' => count($rows),
        ]);

        return to_route('school.students.import.show', $import);
    }

    public function show(StudentImport $import): View
    {
        $classNames = ClassArm::query()->with('level')->get()->mapWithKeys(fn (ClassArm $arm) => [$arm->id => $arm->displayName()]);

        return view('school.students.import.show', [
            'import' => $import,
            'classNames' => $classNames,
            'preview' => array_slice($import->rows, 0, 200),
        ]);
    }

    public function start(StudentImport $import): RedirectResponse
    {
        abort_unless($import->status === ImportStatus::Checked && $import->total_rows > 0, 409);

        $import->update(['status' => ImportStatus::Queued]);
        ImportStudents::dispatch($import->id);

        return to_route('school.students.import.show', $import);
    }

    /**
     * Progress for the import page while the queue works.
     */
    public function status(StudentImport $import): JsonResponse
    {
        return response()->json([
            'status' => $import->status->value,
            'label' => $import->status->label(),
            'percent' => $import->percentDone(),
            'processed' => $import->processed_rows,
            'total' => $import->total_rows,
            'created' => $import->created_count,
            'problems' => count($import->problems),
        ]);
    }

    /**
     * The new logins as CSV, once. They are deleted after download so
     * temporary passwords do not sit in the database.
     */
    public function credentials(StudentImport $import): StreamedResponse|RedirectResponse
    {
        $credentials = $import->credentials;

        if (empty($credentials)) {
            return back()->with('status', 'The logins for this import have already been downloaded.');
        }

        $import->update(['credentials' => null]);
        activity('people')->performedOn($import)->log("downloaded the logins from {$import->file_name}");

        return response()->streamDownload(function () use ($credentials): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Student', 'Class', 'Admission no.', 'Student login', 'Student password', 'Parent', 'Parent login (phone)', 'Parent password'], escape: '');

            foreach ($credentials as $row) {
                fputcsv($out, [
                    $row['name'], $row['class'], $row['admission_no'], $row['student_login'], $row['student_password'],
                    $row['parent'], $row['parent_login'], $row['parent_password'] ?? 'Already has an account',
                ], escape: '');
            }

            fclose($out);
        }, 'logins-'.Str::slug(pathinfo($import->file_name, PATHINFO_FILENAME)).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(StudentImport $import): RedirectResponse
    {
        abort_if($import->status->isInProgress(), 409);

        $import->delete();

        return to_route('school.students.import.create')->with('status', 'Import discarded.');
    }
}
