<?php

namespace App\Http\Controllers;

use App\Enums\SchoolRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolPickerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $schools = $user->activeSchools()->orderBy('name')->get();

        // Role names per school, read straight from Spatie's pivot (roles are scoped by school_id).
        $rolesBySchool = DB::table(config('permission.table_names.model_has_roles'))
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->get(['model_has_roles.school_id', 'roles.name'])
            ->groupBy('school_id')
            ->map(fn ($roles) => $roles->map(fn ($role): string => SchoolRole::tryFrom($role->name)?->label() ?? $role->name)->all());

        return view('app.pick-school', [
            'schools' => $schools,
            'rolesBySchool' => $rolesBySchool,
        ]);
    }
}
