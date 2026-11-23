<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SchoolStatus;
use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Models\School;
use App\Models\User;
use App\Support\Platform\PlatformSettings;
use App\Support\Platform\SchoolDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * The platform console's first page: how many schools, how they're doing, what needs
 * attention (trials ending, new leads), and whether payments and email are working.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $statusCounts = SchoolDirectory::statusCounts();
        $trialsEnding = SchoolDirectory::query()
            ->where('status', SchoolStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now()->addDays(14))
            ->orderBy('trial_ends_at')
            ->limit(6)
            ->get();
        $secret = (string) config('services.paystack.secret_key');

        return view('platform.dashboard', [
            'stats' => [
                ['label' => 'Schools', 'value' => number_format(array_sum($statusCounts)), 'hint' => $statusCounts['active'].' active · '.$statusCounts['trial'].' on trial', 'icon' => 'school', 'tone' => 'brand', 'url' => route('platform.schools.index')],
                ['label' => 'Students on Ta’aleem', 'value' => number_format(SchoolDirectory::totalStudents()), 'hint' => number_format(User::query()->count()).' people can sign in', 'icon' => 'graduation-cap', 'tone' => 'naija'],
                ['label' => 'Trials ending soon', 'value' => number_format($trialsEnding->count()), 'hint' => 'In the next 14 days', 'icon' => 'hourglass', 'tone' => 'gold', 'url' => route('platform.schools.index', ['status' => 'trial'])],
                ['label' => 'New leads', 'value' => number_format(DemoRequest::query()->unhandled()->count()), 'hint' => 'Demo requests to follow up', 'icon' => 'inbox', 'tone' => 'sky', 'url' => route('platform.leads.index')],
            ],
            'statusCounts' => $statusCounts,
            'signups' => $this->signups(),
            'activeDaily' => $this->activeDaily(),
            'trialsEnding' => $trialsEnding,
            'recentSchools' => SchoolDirectory::query()->latest()->limit(5)->get(),
            'recentLeads' => DemoRequest::query()->latest()->limit(5)->get(),
            'platform' => [
                'paystack' => match (true) {
                    str_starts_with($secret, 'sk_live_') => ['Live', 'naija'],
                    str_starts_with($secret, 'sk_test_') => ['Test mode', 'gold'],
                    default => ['Not set up', 'danger'],
                },
                'email' => config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.host'))
                    ? [PlatformSettings::source('mail_host') === 'saved' ? 'SMTP, set in Settings' : 'SMTP, from .env', 'naija']
                    : ['Not sending ('.config('mail.default').')', 'danger'],
                'failedJobs' => DB::table('failed_jobs')->count(),
                'queuedJobs' => DB::table('jobs')->count(),
            ],
        ]);
    }

    /**
     * New schools in each of the last 12 months, oldest first.
     *
     * @return list<array{label: string, value: int, display: string, current: bool}>
     */
    private function signups(): array
    {
        $since = now()->startOfMonth()->subMonths(11);
        $counts = School::query()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->countBy(fn (School $school): string => $school->created_at->format('Y-m'));

        return collect(range(0, 11))->map(function (int $offset) use ($since, $counts): array {
            $month = $since->copy()->addMonths($offset);
            $value = (int) ($counts[$month->format('Y-m')] ?? 0);

            return ['label' => $month->format('M'), 'value' => $value, 'display' => $value.' '.str('school')->plural($value).' in '.$month->format('F Y'), 'current' => $offset === 11];
        })->all();
    }

    /**
     * Schools with any activity on each of the last 30 days.
     *
     * @return list<array{label: string, value: int, display: string, current: bool}>
     */
    private function activeDaily(): array
    {
        $since = today()->subDays(29);
        $counts = DB::table('activity_log')
            ->whereNotNull('school_id')
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(distinct school_id) as schools')
            ->groupBy('day')
            ->pluck('schools', 'day');

        return collect(range(0, 29))->map(function (int $offset) use ($since, $counts): array {
            $day = $since->copy()->addDays($offset);
            $value = (int) ($counts[$day->toDateString()] ?? 0);

            return ['label' => $day->format('j'), 'value' => $value, 'display' => $value.' active on '.$day->format('D j M'), 'current' => $day->isToday()];
        })->all();
    }
}
