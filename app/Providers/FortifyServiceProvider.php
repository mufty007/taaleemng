<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Staff and parents sign in with their email address or phone number.
        // Students sign in with their school code and admission number.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = trim((string) $request->input(Fortify::username()));

            $user = match (true) {
                $request->filled('school_code') => $this->studentUser((string) $request->input('school_code'), $login),
                str_contains($login, '@') => User::query()->where('email', Str::lower($login))->first(),
                default => User::query()->where('phone', User::normalisePhone($login) ?? $login)->first(),
            };

            return $user && Hash::check((string) $request->input('password'), $user->password) ? $user : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });

        RateLimiter::for('demo-requests', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        RateLimiter::for('applications', function (Request $request) {
            return Limit::perHour(20)->by($request->ip());
        });
    }

    private function studentUser(string $schoolCode, string $admissionNo): ?User
    {
        $school = School::query()->where('short_code', Str::upper(trim($schoolCode)))->first();

        if ($school === null) {
            return null;
        }

        $userId = app(CurrentSchool::class)->run($school, fn () => Student::query()
            ->where('admission_no', Str::upper($admissionNo))
            ->value('user_id'));

        return $userId ? User::query()->find($userId) : null;
    }
}
