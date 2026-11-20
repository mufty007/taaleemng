<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    public function index(Request $request): View
    {
        $showHandled = $request->boolean('handled');

        return view('platform.demo-requests.index', [
            'leads' => DemoRequest::query()
                ->when(! $showHandled, fn ($query) => $query->unhandled())
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'showHandled' => $showHandled,
        ]);
    }

    public function update(DemoRequest $demoRequest): RedirectResponse
    {
        $demoRequest->update(['handled_at' => $demoRequest->handled_at ? null : now()]);

        return back()->with('status', $demoRequest->handled_at ? 'Marked as handled.' : 'Moved back to new leads.');
    }
}
