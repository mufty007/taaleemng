<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BroadcastAudience;
use App\Enums\BroadcastLevel;
use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Models\PlatformBroadcast;
use App\Models\School;
use App\Models\User;
use App\Notifications\PlatformBroadcastNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Notices from Ta'aleem to schools: a banner for the chosen people, and optionally an
 * email to every school admin.
 */
class BroadcastController extends Controller
{
    public function index(): View
    {
        return view('platform.broadcasts.index', [
            'broadcasts' => PlatformBroadcast::query()->withCount('dismissedBy')->latest()->paginate(20),
            'schools' => School::query()->orderBy('name')->pluck('name', 'id'),
            'levels' => BroadcastLevel::cases(),
            'audiences' => BroadcastAudience::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('broadcast', [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'level' => ['required', Rule::enum(BroadcastLevel::class)],
            'audience' => ['required', Rule::enum(BroadcastAudience::class)],
            'school_ids' => ['nullable', 'array'],
            'school_ids.*' => ['integer', Rule::exists('schools', 'id')],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'email_admins' => ['boolean'],
        ]);

        $broadcast = PlatformBroadcast::query()->create([
            ...$data,
            'school_ids' => filled($data['school_ids'] ?? null) ? array_map('intval', $data['school_ids']) : null,
            'starts_at' => $data['starts_at'] ?? now(),
            'email_admins' => $request->boolean('email_admins'),
            'created_by' => $request->user()->id,
        ]);

        $emailed = $broadcast->email_admins ? $this->emailAdmins($broadcast) : 0;
        activity('platform')->performedOn($broadcast)->log("broadcast “{$broadcast->title}” to {$broadcast->audience->label()}");

        return to_route('platform.broadcasts.index')->with('status', 'Broadcast published.'.($emailed ? " Emailed {$emailed} school ".str('admin')->plural($emailed).'.' : ''));
    }

    public function end(PlatformBroadcast $broadcast): RedirectResponse
    {
        $broadcast->update(['ends_at' => now()]);
        activity('platform')->performedOn($broadcast)->log("ended the broadcast “{$broadcast->title}”");

        return back()->with('status', 'Broadcast ended. Schools no longer see it.');
    }

    private function emailAdmins(PlatformBroadcast $broadcast): int
    {
        $admins = User::query()
            ->whereNotNull('email')
            ->whereIn('id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', SchoolRole::SchoolAdmin->value)
                ->when($broadcast->school_ids, fn ($query, array $ids) => $query->whereIn('model_has_roles.school_id', $ids))
                ->select('model_has_roles.model_id'))
            ->get();

        Notification::send($admins, new PlatformBroadcastNotification($broadcast->title, $broadcast->body));

        return $admins->count();
    }
}
