<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\Platform\Health;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Platform health (scheduler, jobs, email, Paystack, backups, disk) and the audit log
 * of everything super admins have done.
 */
class HealthController extends Controller
{
    public function index(): View
    {
        return view('platform.health', [
            'checks' => Health::checks(),
            'versions' => Health::versions(),
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(25)->get()
                ->map(fn (object $job): array => [
                    'uuid' => $job->uuid,
                    'name' => class_basename((string) (json_decode((string) $job->payload, true)['displayName'] ?? 'Job')),
                    'queue' => $job->queue,
                    'error' => Str::limit(strtok((string) $job->exception, "\n") ?: 'Unknown error', 220),
                    'failed_at' => $job->failed_at,
                ]),
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        $id = (string) $request->validate(['id' => ['required', 'string', 'max:64']])['id'];

        Artisan::call('queue:retry', ['id' => [$id]]);
        activity('platform')->log($id === 'all' ? 'retried all failed jobs' : "retried failed job {$id}");

        return back()->with('status', $id === 'all' ? 'All failed jobs are back in the queue.' : 'The job is back in the queue.');
    }

    public function forget(Request $request): RedirectResponse
    {
        $id = (string) $request->validate(['id' => ['required', 'uuid']])['id'];

        Artisan::call('queue:forget', ['id' => $id]);
        activity('platform')->log("deleted failed job {$id}");

        return back()->with('status', 'Failed job deleted.');
    }

    /**
     * Everything super admins did: settings, school changes, view-as, support actions.
     */
    public function audit(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('platform.audit', [
            'entries' => Activity::query()
                ->where(fn ($query) => $query->whereIn('log_name', ['platform', 'support'])->orWhere('properties->support', true))
                ->when($search !== '', fn ($query) => $query->where('description', 'like', "%{$search}%"))
                ->with('causer')
                ->latest()
                ->latest('id')
                ->paginate(40)
                ->withQueryString(),
            'search' => $search,
            'schools' => DB::table('schools')->pluck('name', 'id'),
        ]);
    }
}
