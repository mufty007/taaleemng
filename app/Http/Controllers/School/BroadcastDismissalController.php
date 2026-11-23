<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\PlatformBroadcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Close a Ta'aleem broadcast banner for yourself.
 */
class BroadcastDismissalController extends Controller
{
    public function __invoke(Request $request, PlatformBroadcast $broadcast): RedirectResponse|Response
    {
        $broadcast->dismissedBy()->syncWithoutDetaching([$request->user()->id]);

        return $request->expectsJson() ? response()->noContent() : back();
    }
}
