<?php

namespace App\Http\Controllers\School;

use App\Enums\AnnouncementAudience;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\ClassArm;
use App\Models\User;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The notice board. The office posts to everyone, staff, parents, students or a
 * class; teachers post to the classes they teach. Everyone reads what is meant for them.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('school.announcements.index', [
            'announcements' => Announcement::query()->visibleTo($user)
                ->with(['author', 'classArm.level', 'reads' => fn ($query) => $query->where('user_id', $user->id)])
                ->noticeBoardOrder()
                ->paginate(20),
            'canPost' => self::canPost($user),
            'isOffice' => self::isOffice($user),
            'audiences' => self::isOffice($user)
                ? collect(AnnouncementAudience::cases())->mapWithKeys(fn (AnnouncementAudience $audience) => [$audience->value => $audience->label()])->all()
                : [AnnouncementAudience::ClassArm->value => AnnouncementAudience::ClassArm->label()],
            'classOptions' => $this->classOptions($user)->mapWithKeys(fn (ClassArm $arm) => [$arm->id => $arm->displayName()])->all(),
        ]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $user = $request->user();
        abort_unless(Announcement::query()->visibleTo($user)->whereKey($announcement->id)->exists(), 404);

        AnnouncementRead::query()->firstOrCreate(['announcement_id' => $announcement->id, 'user_id' => $user->id], ['read_at' => now()]);

        return view('school.announcements.show', [
            'announcement' => $announcement->load(['author', 'classArm.level']),
            'canDelete' => $this->canDelete($user, $announcement),
            'readCount' => self::isOffice($user) || $announcement->created_by === $user->id ? $announcement->reads()->count() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(self::canPost($user), 403);
        $isOffice = self::isOffice($user);

        $data = $request->validateWithBag('announcement', [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)->only($isOffice ? AnnouncementAudience::cases() : [AnnouncementAudience::ClassArm])],
            'class_arm_id' => ['nullable', 'required_if:audience,class', TenantRule::exists('class_arms'), Rule::in($this->classOptions($user)->pluck('id')->all())],
            'is_pinned' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ], ['class_arm_id.required_if' => 'Choose the class.', 'class_arm_id.in' => 'You can only post to classes you teach.']);

        $announcement = Announcement::query()->create([
            ...$data,
            'class_arm_id' => $data['audience'] === AnnouncementAudience::ClassArm->value ? $data['class_arm_id'] : null,
            'is_pinned' => $isOffice && ($data['is_pinned'] ?? false),
            'published_at' => now(),
            'created_by' => $user->id,
        ]);
        AnnouncementRead::query()->create(['announcement_id' => $announcement->id, 'user_id' => $user->id, 'read_at' => now()]);
        activity('notices')->performedOn($announcement)->log("posted “{$announcement->title}” to {$announcement->audienceLabel()}");

        return to_route('school.announcements.index')->with('status', "Posted to {$announcement->audienceLabel()}.");
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($this->canDelete($request->user(), $announcement), 403);

        $announcement->delete();
        activity('notices')->log("removed the notice “{$announcement->title}”");

        return to_route('school.announcements.index')->with('status', 'Notice removed.');
    }

    /**
     * Clear the bell: mark every notice this person can see as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $unread = Announcement::query()->visibleTo($user)->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))->pluck('id');

        foreach ($unread as $announcementId) {
            AnnouncementRead::query()->create(['announcement_id' => $announcementId, 'user_id' => $user->id, 'read_at' => now()]);
        }

        return back()->with('status', 'All caught up.');
    }

    /**
     * Tick one notice off the compact board without opening it.
     */
    public function markRead(Request $request, Announcement $announcement): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(Announcement::query()->visibleTo($user)->whereKey($announcement->id)->exists(), 404);

        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Marked as read.');
    }

    /**
     * The office can post to anyone; teachers can post to the classes they teach.
     */
    public static function canPost(User $user): bool
    {
        return self::isOffice($user) || ($user->staffRecord()?->classArmIds() ?? []) !== [];
    }

    private static function isOffice(User $user): bool
    {
        return $user->can('academics.manage') || $user->can('school.manage');
    }

    private function canDelete(User $user, Announcement $announcement): bool
    {
        return self::isOffice($user) || $announcement->created_by === $user->id;
    }

    /**
     * @return Collection<int, ClassArm>
     */
    private function classOptions(User $user): Collection
    {
        $session = AcademicSession::current();

        return ClassArm::query()
            ->with('level')
            ->when(! self::isOffice($user), fn ($query) => $query->whereIn('id', $user->staffRecord()?->classArmIds() ?? []))
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session->id ?? 0)])
            ->get()
            ->filter(fn (ClassArm $arm) => $arm->enrolments_count > 0)
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();
    }
}
