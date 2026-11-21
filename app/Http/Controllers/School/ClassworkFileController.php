<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Support\Classwork\ClassworkAccess;
use App\Support\Files\StoredFiles;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opens the files on assignments: the teacher's worksheet and each student's handed-in work.
 */
class ClassworkFileController extends Controller
{
    public function assignment(Request $request, Assignment $assignment, int $index): StreamedResponse
    {
        abort_unless(ClassworkAccess::canSeeAssignment($request->user(), $assignment), 404);

        return StoredFiles::respond($assignment->files[$index] ?? abort(404), $request->boolean('download'));
    }

    public function submission(Request $request, AssignmentSubmission $submission, int $index): StreamedResponse
    {
        abort_unless(ClassworkAccess::canSeeSubmission($request->user(), $submission), 404);

        return StoredFiles::respond($submission->files[$index] ?? abort(404), $request->boolean('download'));
    }
}
