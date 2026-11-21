<?php

namespace App\Http\Controllers\Admissions;

use App\Actions\Admissions\SubmitApplication;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreApplicationRequest;
use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\SchoolSetting;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A school's public admission form, linked from its website or shared on WhatsApp.
 */
class ApplicationFormController extends Controller
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function show(): View
    {
        $session = AcademicSession::forAdmissions();

        if ($session === null || ! SchoolSetting::valueFor('admissions_open')) {
            return view('admissions.closed', ['school' => $this->currentSchool->getOrFail()]);
        }

        return view('admissions.apply', [
            'school' => $this->currentSchool->getOrFail(),
            'session' => $session,
            'levels' => ClassLevel::query()->orderBy('position')->pluck('name', 'id'),
            'genders' => collect(Gender::cases())->mapWithKeys(fn (Gender $gender) => [$gender->value => $gender->label()])->all(),
            'relationships' => collect(GuardianRelationship::cases())->mapWithKeys(fn (GuardianRelationship $relationship) => [$relationship->value => $relationship->label()])->all(),
        ]);
    }

    public function store(Request $request, SubmitApplication $submit): RedirectResponse
    {
        abort_unless(SchoolSetting::valueFor('admissions_open') && AcademicSession::forAdmissions(), 404);

        // Bots fill every field, including this hidden one.
        if (filled($request->input('website'))) {
            return to_route('apply.show');
        }

        $data = $request->validate(StoreApplicationRequest::fields(), [], StoreApplicationRequest::fieldNames());
        $applicant = $submit->handle($data, 'online');

        return to_route('apply.thanks')->with('application', [
            'number' => $applicant->application_no,
            'child' => $applicant->first_name,
            'class' => $applicant->classLevel()->value('name'),
            'phone' => $applicant->guardian_phone,
        ]);
    }

    public function thanks(Request $request): View|RedirectResponse
    {
        $application = $request->session()->get('application');

        if (! is_array($application)) {
            return to_route('apply.show');
        }

        return view('admissions.thanks', ['school' => $this->currentSchool->getOrFail(), 'application' => $application]);
    }
}
