<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSchoolContext;
use App\Http\Middleware\EnsureSchoolIsActive;
use App\Http\Middleware\ResolvePublicSchool;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'school' => EnsureSchoolContext::class,
            'school.active' => EnsureSchoolIsActive::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'school.public' => ResolvePublicSchool::class,
        ]);

        // The school must be in context before route model binding runs,
        // otherwise binding {student} etc. hits the tenant scope with no school.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureSchoolContext::class);

        $middleware->redirectUsersTo('/app');

        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // An invitation link past its week: explain, rather than a bare 403.
        $exceptions->render(fn (InvalidSignatureException $exception, Request $request) => $request->routeIs('invitation.*')
            ? response()->view('auth.invitation-used', ['expired' => true], 403)
            : null);
    })->create();
