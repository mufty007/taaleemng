<?php

namespace App\Actions\Platform;

use App\Enums\MembershipStatus;
use App\Enums\SchoolRole;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;

/**
 * Give a user an active membership of a school with the given role.
 */
class AddSchoolMember
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(School $school, User $user, SchoolRole $role): void
    {
        $user->schools()->syncWithoutDetaching([
            $school->id => ['status' => MembershipStatus::Active, 'joined_at' => now()],
        ]);

        $this->currentSchool->run($school, function () use ($user, $role): void {
            $user->unsetRelation('roles');
            $user->assignRole($role);
        });
    }
}
