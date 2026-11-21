<?php

namespace App\Actions\People;

use App\Enums\SchoolRole;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;

/**
 * Change a staff member’s school role while keeping parent/student roles intact.
 */
class ChangeMemberRole
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(User $user, SchoolRole $role, ?User $actor = null): void
    {
        $this->currentSchool->getOrFail();

        if (! $role->isStaff()) {
            throw ValidationException::withMessages([
                'role' => 'Choose a staff role. Parent and student access comes from enrolment.',
            ]);
        }

        if ($actor !== null && $user->is($actor)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role.',
            ]);
        }

        $user->unsetRelation('roles');

        $staffRoles = $user->getRoleNames()
            ->map(fn (string $name): ?SchoolRole => SchoolRole::tryFrom($name))
            ->filter(fn (?SchoolRole $schoolRole): bool => $schoolRole?->isStaff() ?? false);

        if ($staffRoles->isEmpty()) {
            throw ValidationException::withMessages([
                'role' => 'Only people on the staff list can have their role changed here.',
            ]);
        }

        if ($staffRoles->contains(SchoolRole::SchoolAdmin) && $role !== SchoolRole::SchoolAdmin
            && User::role(SchoolRole::SchoolAdmin->value)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Keep at least one school admin. Promote someone else first.',
            ]);
        }

        if ($staffRoles->contains($role) && $staffRoles->count() === 1) {
            return;
        }

        $kept = $user->getRoleNames()
            ->filter(fn (string $name): bool => ! (SchoolRole::tryFrom($name)?->isStaff() ?? false))
            ->values()
            ->all();

        $user->syncRoles([...$kept, $role]);
        $user->unsetRelation('roles');

        activity('people')->performedOn($user)->log("changed {$user->name}’s role to {$role->label()}");
    }
}
