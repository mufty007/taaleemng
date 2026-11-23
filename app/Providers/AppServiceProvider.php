<?php

namespace App\Providers;

use App\Models\Term;
use App\Models\User;
use App\Support\Platform\PlatformSettings;
use App\Support\Platform\ViewAs;
use App\Support\Reports\ReportCatalogue;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentSchool::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Paystack keys and the central SMTP account, as set by the super admin in the platform console.
        // Not while caching config: the cache must hold only .env values, so clearing a saved setting works.
        if (! $this->app->runningConsoleCommand('config:cache', 'optimize')) {
            PlatformSettings::apply();
        }

        // Super admins open any school in support mode, with a school admin's reach. Not the
        // parent/student portal abilities, and not chat: staff conversations stay private.
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->is_platform_admin || str_starts_with($ability, 'portal.') || str_starts_with($ability, 'chat.') || $ability === 'cbt.take') {
                return null;
            }

            return true;
        });

        // The Reports page is open to anyone allowed at least one report on it.
        Gate::define('view-reports', fn (User $user): bool => ReportCatalogue::visibleTo($user) !== []);

        // Stamp every activity log row with the school in context and the client IP, and flag
        // what Ta'aleem support did (a super admin in support mode, or viewing as someone).
        Activity::creating(function (Activity $activity): void {
            $activity->school_id ??= $this->app->make(CurrentSchool::class)->id();
            $activity->ip_address ??= request()->ip();

            $viewingAs = ViewAs::active();
            $actor = auth()->user();
            $bySuperAdmin = $actor instanceof User && $actor->is_platform_admin && (int) $activity->causer_id === $actor->id;

            if ($viewingAs || $bySuperAdmin) {
                $activity->properties = collect($activity->properties ?? [])->put('support', true)
                    ->when($viewingAs, fn ($properties) => $properties->put('support_admin_id', request()->session()->get(ViewAs::ADMIN)));
            }
        });

        Event::listen(function (Login $event): void {
            // Switching into or out of "view as" is not anyone's own sign-in.
            if (! $event->user instanceof User || ViewAs::switching()) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

            activity('auth')->causedBy($event->user)->log('logged in');
        });

        View::composer('*', function ($view): void {
            $view->with('currentSchool', $this->app->make(CurrentSchool::class)->get());
        });

        View::composer('components.layouts.app', function ($view): void {
            $view->with('switchableSchools', auth()->user()?->activeSchools()->orderBy('name')->get() ?? collect());
            $view->with('currentTerm', $this->app->make(CurrentSchool::class)->check() ? Term::current() : null);
        });
    }
}
