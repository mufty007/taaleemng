<?php

namespace App\Actions\People;

use App\Actions\Platform\AddSchoolMember;
use App\Enums\SchoolRole;
use App\Enums\StaffStatus;
use App\Models\Staff;
use App\Models\User;
use App\Support\Numbering\SequenceGenerator;
use App\Support\TemporaryPassword;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Add an employee: a staff record, a login (reusing one they already have on
 * Ta'aleem), and a role in this school.
 */
class AddStaffMember
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private SequenceGenerator $sequences,
        private AddSchoolMember $addSchoolMember,
    ) {}

    /**
     * @param  array{title?: ?string, first_name: string, last_name: string, other_names?: ?string, gender: string, phone?: ?string, email?: ?string, designation: string, department?: ?string, employment_type?: string, date_employed?: ?string, qualification?: ?string}  $data
     * @param  string|null  $password  Fixed password for a new login (demo seeding); generated otherwise.
     * @return array{staff: Staff, password: ?string}
     */
    public function handle(array $data, SchoolRole $role, ?string $password = null): array
    {
        $school = $this->currentSchool->getOrFail();
        $phone = filled($data['phone'] ?? null) ? (User::normalisePhone($data['phone']) ?? $data['phone']) : null;
        $email = filled($data['email'] ?? null) ? Str::lower($data['email']) : null;

        return DB::transaction(function () use ($school, $data, $role, $password, $phone, $email): array {
            $user = ($email ? User::query()->where('email', $email)->first() : null)
                ?? ($phone ? User::query()->where('phone', $phone)->first() : null);

            if ($user !== null && Staff::query()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'This person is already on your staff list.',
                ]);
            }

            $newPassword = null;

            if ($user === null) {
                $newPassword = $password ?? TemporaryPassword::generate();
                $user = User::query()->create([
                    'name' => trim($data['first_name'].' '.$data['last_name']),
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $newPassword,
                ]);
            }

            $staff = Staff::query()->create([
                ...$data,
                'phone' => $phone,
                'email' => $email,
                'user_id' => $user->id,
                'staff_no' => $this->sequences->next('staff_no'),
                'status' => StaffStatus::Active,
            ]);

            $this->addSchoolMember->handle($school, $user, $role);

            activity('people')->performedOn($staff)->log("added {$staff->fullName()} as {$role->label()}");

            return ['staff' => $staff, 'password' => $newPassword];
        });
    }
}
