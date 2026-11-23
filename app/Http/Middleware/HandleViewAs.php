<?php

namespace App\Http\Middleware;

use App\Support\Platform\ViewAs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a super admin is viewing as someone: keep that person's account settings
 * (password, profile, two-step sign-in) and private chat closed, and make "Log out"
 * return the admin to the platform console instead of ending the session.
 */
class HandleViewAs
{
    /** @var list<string> */
    private const BLOCKED = [
        'account',
        'user-password.update',
        'user-profile-information.update',
        'two-factor.enable',
        'two-factor.disable',
        'two-factor.confirm',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'password.confirm',
        'password.confirm.store',
        'school.chat*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! ViewAs::active($request)) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return ViewAs::stop($request);
        }

        if ($request->routeIs(...self::BLOCKED)) {
            $message = 'Not available while viewing as someone: their account settings and chats stay private.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('app')->with('status', $message);
        }

        return $next($request);
    }
}
