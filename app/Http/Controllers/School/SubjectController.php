<?php

namespace App\Http\Controllers\School;

use App\Enums\StaffStatus;
use App\Enums\SubjectCategory;
use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Staff;
use App\Models\Subject;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(): View
    {
        $subjects = Subject::query()
            ->withCount('classSubjects')
            ->withCount(['classSubjects as unassigned_count' => fn ($query) => $query->whereNull('teacher_id')])
            ->orderBy('name')
            ->get();

        $arms = ClassArm::query()->with(['level', 'stream'])->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->map(fn (ClassArm $arm) => ['id' => $arm->id, 'name' => $arm->displayName(), 'section' => $arm->level->section->label(), 'stream' => $arm->stream?->name])
            ->values();

        // subject id => [class arm id => teacher id|null], for the "Classes" panel.
        $assignments = ClassSubject::query()->get(['subject_id', 'class_arm_id', 'teacher_id'])
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (ClassSubject $row) => [$row->class_arm_id => $row->teacher_id]));

        return view('school.subjects.index', [
            'subjects' => $subjects,
            'arms' => $arms,
            'assignments' => $assignments,
            'teacherOptions' => Staff::query()->where('status', StaffStatus::Active)->orderBy('last_name')->get()
                ->mapWithKeys(fn (Staff $staff) => [$staff->id => $staff->fullName()])->all(),
            'categories' => collect(SubjectCategory::cases())->mapWithKeys(fn ($category) => [$category->value => $category->label()])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('addSubject', [
            'name' => ['required', 'string', 'max:80', TenantRule::unique('subjects', 'name')],
            'code' => ['required', 'string', 'max:12', 'alpha_dash', TenantRule::unique('subjects', 'code')],
            'category' => ['required', Rule::enum(SubjectCategory::class)],
        ]);

        $subject = Subject::query()->create([...$data, 'code' => strtoupper($data['code'])]);

        return back()->with('status', "{$subject->name} has been added. Add it to classes from each class page.");
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validateWithBag('editSubject', [
            'name' => ['required', 'string', 'max:80', TenantRule::unique('subjects', 'name')->ignore($subject->id)],
            'code' => ['required', 'string', 'max:12', 'alpha_dash', TenantRule::unique('subjects', 'code')->ignore($subject->id)],
            'category' => ['required', Rule::enum(SubjectCategory::class)],
        ]);

        $subject->update([...$data, 'code' => strtoupper($data['code'])]);

        return back()->with('status', "{$subject->name} saved.");
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        if ($subject->classSubjects()->exists()) {
            return back()->with('status', "{$subject->name} is still taught in some classes. Untick them under “Classes” first.");
        }

        $subject->delete();

        return back()->with('status', "{$subject->name} has been deleted.");
    }

    /**
     * Choose, from the subject's side, every class that takes it and who teaches it there.
     */
    public function syncClasses(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'classes' => ['array'],
            'classes.*.taught' => ['boolean'],
            'classes.*.teacher_id' => ['nullable', TenantRule::exists('staff')],
        ]);

        $arms = ClassArm::query()->pluck('id')->flip();
        $existing = $subject->classSubjects()->get()->keyBy('class_arm_id');

        DB::transaction(function () use ($data, $arms, $existing, $subject): void {
            foreach ($data['classes'] ?? [] as $armId => $row) {
                if (! $arms->has((int) $armId)) {
                    continue;
                }

                $current = $existing->get((int) $armId);
                $teacherId = $row['teacher_id'] ?? null;

                if (! ($row['taught'] ?? false)) {
                    $current?->delete();
                } elseif ($current) {
                    $current->update(['teacher_id' => $teacherId]);
                } else {
                    $subject->classSubjects()->create(['class_arm_id' => (int) $armId, 'teacher_id' => $teacherId, 'is_compulsory' => true]);
                }
            }
        });

        activity('academics')->performedOn($subject)->log("updated the classes that take {$subject->name}");

        return back()->with('status', "Classes for {$subject->name} saved.");
    }

    public function assign(Request $request, ClassArm $classArm): RedirectResponse
    {
        $data = $request->validateWithBag('assignSubject', [
            'subject_id' => ['required', TenantRule::exists('subjects'), Rule::unique('class_subjects')->where('class_arm_id', $classArm->id)],
            'teacher_id' => ['nullable', TenantRule::exists('staff')],
        ], ['subject_id.unique' => 'This class already takes that subject.']);

        $classArm->classSubjects()->create([...$data, 'is_compulsory' => true]);

        return back()->with('status', 'Subject added to the class.');
    }

    public function updateTeacher(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['nullable', TenantRule::exists('staff')],
        ]);

        $classSubject->update($data);
        $classSubject->load(['subject', 'teacher', 'classArm.level']);

        activity('academics')->performedOn($classSubject)->log("assigned {$classSubject->subject->name} in {$classSubject->classArm->displayName()}");

        return back()->with('status', $classSubject->teacher
            ? "{$classSubject->teacher->fullName()} now teaches {$classSubject->subject->name}."
            : "{$classSubject->subject->name} has no teacher now.");
    }

    public function unassign(ClassSubject $classSubject): RedirectResponse
    {
        $classSubject->delete();

        return back()->with('status', 'Subject removed from the class.');
    }
}
