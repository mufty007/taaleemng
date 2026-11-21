<?php

namespace App\Actions\People;

use App\Enums\SchoolRole;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Update an employee’s record and, optionally, their role in this school.
 */
class UpdateStaffMember
{
    public function __construct(private ChangeMemberRole $changeMemberRole) {}

    /**
     * @param  array{title?: ?string, first_name: string, last_name: string, other_names?: ?string, gender: string, phone?: ?string, email?: ?string, designation: string, department?: ?string, employment_type?: string, date_employed?: ?string, qualification?: ?string, status?: string}  $data
     */
    public function handle(Staff $staff, array $data, ?SchoolRole $role = null, ?User $actor = null): Staff
    {
        $phone = filled($data['phone'] ?? null) ? (User::normalisePhone($data['phone']) ?? $data['phone']) : null;
        $email = filled($data['email'] ?? null) ? Str::lower($data['email']) : null;

        return DB::transaction(function () use ($staff, $data, $role, $actor, $phone, $email): Staff {
            $this->guardContactUniqueness($staff, $email, $phone);

            $staff->update([
                ...$data,
                'phone' => $phone,
                'email' => $email,
            ]);

            if ($staff->user) {
                $staff->user->forceFill([
                    'name' => trim($staff->first_name.' '.$staff->last_name),
                    'email' => $email,
                    'phone' => $phone,
                ])->save();
            }

            if ($role !== null && $staff->user) {
                $this->changeMemberRole->handle($staff->user, $role, $actor);
            }

            activity('people')->performedOn($staff)->log("updated {$staff->fullName()}’s record");

            return $staff->refresh();
        });
    }

    private function guardContactUniqueness(Staff $staff, ?string $email, ?string $phone): void
    {
        if ($email !== null && User::query()->where('email', $email)->whereKeyNot($staff->user_id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Another account already uses this email.',
            ]);
        }

        if ($phone !== null && User::query()->where('phone', $phone)->whereKeyNot($staff->user_id)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'Another account already uses this phone number.',
            ]);
        }

        if ($email !== null && Staff::query()->where('email', $email)->whereKeyNot($staff->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Someone on your staff list already has this email.',
            ]);
        }

        if ($phone !== null && Staff::query()->where('phone', $phone)->whereKeyNot($staff->id)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'Someone on your staff list already has this phone number.',
            ]);
        }
    }
}
