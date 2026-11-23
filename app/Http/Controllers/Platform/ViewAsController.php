<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Support\Platform\ViewAs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "View as" a member of a school, and back. See App\Support\Platform\ViewAs.
 */
class ViewAsController extends Controller
{
    public function start(Request $request, School $school, User $user): RedirectResponse
    {
        abort_if(ViewAs::active($request), 409, 'Stop viewing as the current person first.');
        abort_if($user->is_platform_admin, 403, 'Super admins can’t be viewed as.');
        abort_unless($user->activeSchools()->whereKey($school->id)->exists(), 404);

        return ViewAs::start($request, $request->user(), $school, $user);
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless(ViewAs::active($request), 404);

        return ViewAs::stop($request);
    }
}
