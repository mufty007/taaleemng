<?php

namespace App\Support\Permissions;

use App\Enums\SchoolRole;
use App\Models\Staff;
use App\Models\User;

/**
 * Which staff roles a user may hand out. You can only give someone a role whose every
 * permission you already hold, so a principal can add teachers and head teachers but
 * cannot create a bursar (payroll) or another school admin.
 */
final class RoleGrants
{
    /**
     * @return list<SchoolRole>
     */
    public static function grantableBy(User $user): array
    {
        return array_values(array_filter(
            SchoolRole::cases(),
            fn (SchoolRole $role): bool => self::canGrant($user, $role),
        ));
    }

    public static function canGrant(User $user, SchoolRole $role): bool
    {
        if (! $role->isStaff()) {
            return false;
        }

        foreach (PermissionCatalogue::forRole($role) as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The staff role a staff member's login holds in this school, if any.
     */
    public static function currentRole(Staff $staff): ?SchoolRole
    {
        return $staff->user?->getRoleNames()
            ->map(fn (string $name): ?SchoolRole => SchoolRole::tryFrom($name))
            ->first(fn (?SchoolRole $role): bool => $role?->isStaff() ?? false);
    }
}
