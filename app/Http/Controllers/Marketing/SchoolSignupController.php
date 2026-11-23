<?php

namespace App\Http\Controllers\Marketing;

use App\Actions\Platform\CreateSchool;
use App\Enums\BillingModel;
use App\Enums\ClassStructure;
use App\Enums\Plan;
use App\Enums\SchoolType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Billing\PlanCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Public self-serve signup: create a school on trial, pick a plan, sign the admin in.
 */
class SchoolSignupController extends Controller
{
    public function create(): View
    {
        return view('marketing.signup', [
            'plans' => PlanCatalog::publicPlans(),
            'schoolTypes' => collect(SchoolType::cases())->mapWithKeys(fn (SchoolType $type) => [$type->value => $type->label()])->all(),
            'billingModels' => BillingModel::options(),
            'classStructures' => ClassStructure::options(),
        ]);
    }

    public function store(Request $request, CreateSchool $createSchool): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:120'],
            'school_type' => ['required', Rule::enum(SchoolType::class)],
            'state' => ['nullable', 'string', 'max:60'],
            'plan' => ['required', Rule::in(array_map(fn (Plan $plan) => $plan->value, Plan::publicTiers()))],
            'billing_model' => ['required', Rule::enum(BillingModel::class)],
            'billing_cycle' => ['required', Rule::in(['termly', 'annual'])],
            'class_structure' => ['required', Rule::enum(ClassStructure::class)],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $school = $createSchool->handle([
            'name' => $data['school_name'],
            'school_type' => SchoolType::from($data['school_type']),
            'state' => $data['state'] ?? null,
            'plan' => Plan::from($data['plan']),
            'billing_model' => BillingModel::from($data['billing_model']),
            'billing_cycle' => $data['billing_cycle'],
            'class_structure' => ClassStructure::from($data['class_structure']),
        ], [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['password'],
        ], sendPasswordReset: false);

        $admin = User::query()->where('email', strtolower($data['admin_email']))->firstOrFail();

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put('last_school', $school->slug);

        return redirect()->route('school.dashboard', $school)
            ->with('status', 'Welcome to Ta’aleem. Your '.$school->plan->label().' trial is ready — finish the setup checklist to get going.');
    }
}
