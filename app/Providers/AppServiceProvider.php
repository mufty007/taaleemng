<?php

namespace App\Providers;

use App\Models\Term;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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

        // Stamp every activity log row with the school in context and the client IP.
        Activity::creating(function (Activity $activity): void {
            $activity->school_id ??= $this->app->make(CurrentSchool::class)->id();
            $activity->ip_address ??= request()->ip();
        });

        Event::listen(function (Login $event): void {
            if (! $event->user instanceof User) {
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
