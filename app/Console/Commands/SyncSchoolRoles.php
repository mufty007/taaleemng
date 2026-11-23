<?php

namespace App\Console\Commands;

use App\Actions\Platform\SeedSchoolRoles;
use App\Models\School;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bring every school's role templates up to date after PermissionCatalogue changes.
 * deploy/deploy.sh runs it on each release.
 */
#[Signature('taaleem:sync-roles')]
#[Description('Refresh every school’s role templates from the permission catalogue')]
class SyncSchoolRoles extends Command
{
    public function handle(SeedSchoolRoles $seedSchoolRoles): int
    {
        $count = 0;

        School::query()->orderBy('id')->each(function (School $school) use ($seedSchoolRoles, &$count): void {
            $seedSchoolRoles->handle($school);
            $count++;
        });

        $this->components->info("Refreshed role templates for {$count} ".str('school')->plural($count).'.');

        return self::SUCCESS;
    }
}
