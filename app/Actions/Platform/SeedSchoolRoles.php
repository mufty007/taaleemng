<?php

namespace App\Actions\Platform;

use App\Enums\SchoolRole;
use App\Models\School;
use App\Support\Permissions\PermissionCatalogue;
use App\Support\Tenancy\CurrentSchool;
use Spatie\Permission\Models\Role;

/**
 * Create (or refresh) the system role templates for one school.
 */
class SeedSchoolRoles
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private SyncPermissions $syncPermissions,
    ) {}

    public function handle(School $school): void
    {
        $this->syncPermissions->handle();

        $this->currentSchool->run($school, function (): void {
            foreach (SchoolRole::cases() as $role) {
                Role::findOrCreate($role->value, 'web')
                    ->syncPermissions(PermissionCatalogue::forRole($role));
            }
        });
    }
}
