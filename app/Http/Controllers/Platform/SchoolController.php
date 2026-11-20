<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\CreateSchool;
use App\Enums\MembershipStatus;
use App\Enums\SchoolType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSchoolRequest;
use App\Models\School;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $schools = School::query()
            ->withCount(['users' => fn ($query) => $query->where('school_user.status', MembershipStatus::Active->value)])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('state', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('platform.schools.index', [
            'schools' => $schools,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('platform.schools.create', [
            'schoolTypes' => SchoolType::cases(),
            'states' => config('nigeria.states'),
        ]);
    }

    public function store(StoreSchoolRequest $request, CreateSchool $createSchool): RedirectResponse
    {
        $data = $request->validated();

        $school = $createSchool->handle(
            [
                'name' => $data['name'],
                'school_type' => $data['school_type'],
                'state' => $data['state'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ],
            ['name' => $data['admin_name'], 'email' => $data['admin_email']],
        );

        return redirect()
            ->route('platform.schools.index')
            ->with('status', "{$school->name} is set up. We’ve emailed {$data['admin_email']} a link to choose a password.");
    }
}
