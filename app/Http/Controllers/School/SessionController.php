<?php

namespace App\Http\Controllers\School;

use App\Actions\Academic\MakeTermCurrent;
use App\Actions\Academic\StartSession;
use App\Enums\TermStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Term;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(): View
    {
        $sessions = AcademicSession::query()
            ->with('terms')
            ->withCount('enrolments')
            ->orderByDesc('starts_on')
            ->get();

        $latestYear = (int) substr((string) $sessions->first()?->name, 0, 4);

        return view('school.sessions.index', [
            'sessions' => $sessions,
            'nextStartYear' => $latestYear ? $latestYear + 1 : (int) now()->subMonths(8)->format('Y'),
        ]);
    }

    public function store(Request $request, StartSession $startSession): RedirectResponse
    {
        $data = $request->validate([
            'start_year' => ['required', 'integer', 'between:2000,'.(now()->year + 2)],
            'make_current' => ['boolean'],
        ]);

        $session = $startSession->handle((int) $data['start_year'], (bool) ($data['make_current'] ?? false));

        return back()->with('status', "The {$session->name} session has been created with three terms.");
    }

    public function makeCurrent(Term $term, MakeTermCurrent $makeTermCurrent): RedirectResponse
    {
        $makeTermCurrent->handle($term);

        return back()->with('status', "{$term->name} {$term->academicSession->name} is now the current term.");
    }

    public function update(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validateWithBag("term{$term->id}", [
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'mid_term_starts_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'before_or_equal:ends_on'],
            'mid_term_ends_on' => ['nullable', 'date', 'after_or_equal:mid_term_starts_on', 'before_or_equal:ends_on'],
            'next_term_begins_on' => ['nullable', 'date', 'after:ends_on'],
            'school_days' => ['nullable', 'integer', 'between:1,366'],
        ]);

        $term->update($data);
        activity('academics')->performedOn($term)->log("changed the dates of {$term->name}");

        return back()->with('status', "{$term->name} saved.");
    }

    public function toggleStatus(Term $term): RedirectResponse
    {
        $closing = $term->status === TermStatus::Open;

        $term->update(['status' => $closing ? TermStatus::Closed : TermStatus::Open]);
        activity('academics')->performedOn($term)->log(($closing ? 'closed ' : 'reopened ').$term->name);

        return back()->with('status', $closing
            ? "{$term->name} is closed. Scores and attendance for it can no longer be changed."
            : "{$term->name} is open again.");
    }
}
