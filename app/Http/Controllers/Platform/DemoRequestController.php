<?php

namespace App\Http\Controllers\Platform;

use App\Enums\DemoRequestKind;
use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Website leads: demo bookings, trial requests and questions from the marketing site.
 */
class DemoRequestController extends Controller
{
    public function index(Request $request): View
    {
        $showHandled = $request->boolean('handled');
        $kind = $request->query('kind');
        $source = $request->query('source');

        $leads = DemoRequest::query()
            ->when(! $showHandled, fn ($query) => $query->unhandled())
            ->when(filled($kind) && DemoRequestKind::tryFrom((string) $kind), fn ($query) => $query->where('kind', $kind))
            ->when(filled($source), fn ($query) => $query->where('source', $source))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('platform.demo-requests.index', [
            'leads' => $leads,
            'showHandled' => $showHandled,
            'kindFilter' => is_string($kind) ? $kind : null,
            'sourceFilter' => is_string($source) ? $source : null,
            'kinds' => DemoRequestKind::cases(),
            'sources' => [
                'hero' => 'Home page (top)',
                'cta' => 'Home page (bottom)',
                'contact' => 'Contact page',
            ],
            'stats' => [
                'new' => DemoRequest::query()->unhandled()->count(),
                'demos' => DemoRequest::query()->unhandled()->where('kind', DemoRequestKind::Demo)->count(),
                'trials' => DemoRequest::query()->unhandled()->where('kind', DemoRequestKind::Trial)->count(),
                'questions' => DemoRequest::query()->unhandled()->where('kind', DemoRequestKind::Question)->count(),
            ],
        ]);
    }

    public function update(Request $request, DemoRequest $demoRequest): RedirectResponse
    {
        $action = (string) $request->input('action', 'toggle');

        if ($action === 'notes') {
            $data = $request->validateWithBag('lead', [
                'notes' => ['nullable', 'string', 'max:5000'],
            ]);
            $demoRequest->update(['notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null]);

            return back()->with('status', 'Lead notes saved.');
        }

        if ($action === 'kind') {
            $data = $request->validateWithBag('lead', [
                'kind' => ['required', Rule::enum(DemoRequestKind::class)],
            ]);
            $demoRequest->update(['kind' => DemoRequestKind::from($data['kind'])]);

            return back()->with('status', 'Lead type updated.');
        }

        $demoRequest->update(['handled_at' => $demoRequest->handled_at ? null : now()]);

        return back()->with('status', $demoRequest->handled_at ? 'Marked as handled.' : 'Moved back to new leads.');
    }
}
