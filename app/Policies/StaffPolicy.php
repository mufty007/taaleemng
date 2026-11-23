<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;
use App\Support\Permissions\RoleGrants;

/**
 * Editing a staff record also edits their login (email, phone, role), so it is limited to
 * people whose role you could have given yourself: a principal can edit teachers but not
 * the bursar or the school admin.
 */
class StaffPolicy
{
    public function update(User $user, Staff $staff): bool
    {
        if (! $user->can('staff.manage')) {
            return false;
        }

        $role = RoleGrants::currentRole($staff);

        return $role === null || RoleGrants::canGrant($user, $role);
    }
}
