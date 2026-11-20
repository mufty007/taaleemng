<?php

namespace App\Actions\Platform;

use App\Support\Permissions\PermissionCatalogue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Make sure every permission in the catalogue exists. Safe to run repeatedly.
 */
class SyncPermissions
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        foreach (PermissionCatalogue::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->registrar->forgetCachedPermissions();
    }
}
