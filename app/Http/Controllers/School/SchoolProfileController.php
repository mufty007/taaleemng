<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolProfileController extends Controller
{
    public function edit(CurrentSchool $currentSchool): View
    {
        return view('school.profile.edit', [
            'school' => $currentSchool->getOrFail(),
            'states' => array_combine(config('nigeria.states'), config('nigeria.states')),
        ]);
    }

    public function update(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $school = $currentSchool->getOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'motto' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(config('nigeria.states'))],
            'lga' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'website' => ['nullable', 'url', 'max:160'],
        ]);

        $school->update($data);
        activity('school')->performedOn($school)->log('updated the school information');

        return back()->with('status', 'School information saved. It will appear on report cards and receipts.');
    }
}
