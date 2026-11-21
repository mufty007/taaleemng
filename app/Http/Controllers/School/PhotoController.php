<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Student;
use App\Support\Files\PassportPhoto;
use App\Support\Files\StoredFiles;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Passport photos for student and staff records. They are private files, shown
 * only to people who can see the record.
 */
class PhotoController extends Controller
{
    public function student(Student $student): StreamedResponse
    {
        Gate::authorize('view', $student);

        return $this->send($student->photo_path);
    }

    public function staff(Request $request, Staff $staff): StreamedResponse
    {
        abort_unless($request->user()->isStaffInCurrentSchool() || $request->user()->is_platform_admin, 403);

        return $this->send($staff->photo_path);
    }

    public function updateStudent(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $student->update(['photo_path' => $this->replace($request, $student->photo_path, 'students')]);

        return back()->with('status', $student->photo_path ? "Photo saved for {$student->first_name}." : 'Photo removed.');
    }

    public function updateStaff(Request $request, Staff $staff): RedirectResponse
    {
        $staff->update(['photo_path' => $this->replace($request, $staff->photo_path, 'staff')]);

        return back()->with('status', $staff->photo_path ? "Photo saved for {$staff->fullName()}." : 'Photo removed.');
    }

    private function replace(Request $request, ?string $old, string $folder): ?string
    {
        $remove = $request->boolean('remove');

        if (! $remove) {
            $request->validate(PassportPhoto::rules(), ['photo.required' => 'Choose a photo to upload.', 'photo.image' => 'The photo must be a picture (JPG, PNG or WebP).']);
        }

        if ($old) {
            Storage::disk(StoredFiles::DISK)->delete($old);
        }

        return $remove ? null : PassportPhoto::store($request->file('photo'), 'schools/'.app(CurrentSchool::class)->id().'/photos/'.$folder);
    }

    private function send(?string $path): StreamedResponse
    {
        abort_if($path === null || ! Storage::disk(StoredFiles::DISK)->exists($path), 404);

        return Storage::disk(StoredFiles::DISK)->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
