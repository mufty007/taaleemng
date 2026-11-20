<?php

namespace Database\Seeders;

use App\Actions\Platform\AddSchoolMember;
use App\Actions\Platform\SeedSchoolRoles;
use App\Actions\Platform\SyncPermissions;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Enums\SchoolType;
use App\Models\DemoRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development. Every account's password is "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(SyncPermissions $syncPermissions, SeedSchoolRoles $seedSchoolRoles, AddSchoolMember $addMember): void
    {
        $syncPermissions->handle();

        User::factory()->platformAdmin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@taaleem.test',
        ]);

        $greenfield = School::factory()->create([
            'name' => 'Greenfield Academy',
            'slug' => 'greenfield',
            'short_code' => 'GFA',
            'school_type' => SchoolType::Combined,
            'state' => 'FCT',
            'address' => '12 Aminu Kano Crescent, Wuse 2, Abuja',
            'phone' => '+2348031234567',
            'email' => 'info@greenfield.test',
            'motto' => 'Knowledge and character',
            'status' => SchoolStatus::Active,
        ]);

        $alNoor = School::factory()->create([
            'name' => 'Al-Noor Model School',
            'slug' => 'al-noor',
            'short_code' => 'ANM',
            'school_type' => SchoolType::Islamiyya,
            'state' => 'Kano',
            'phone' => '+2348051112222',
            'email' => 'office@alnoor.test',
            'status' => SchoolStatus::Trial,
            'trial_ends_at' => now()->addDays(21),
        ]);

        foreach ([$greenfield, $alNoor] as $school) {
            $seedSchoolRoles->handle($school);
        }

        $people = [
            ['Ngozi Okafor', 'proprietor@greenfield.test', null, [[$greenfield, SchoolRole::SchoolAdmin]]],
            ['Mrs. Funke Adebayo', 'principal@greenfield.test', null, [[$greenfield, SchoolRole::Principal]]],
            ['Mrs. Grace Udo', 'bursar@greenfield.test', null, [[$greenfield, SchoolRole::Bursar]]],
            ['Mr. Emeka Okonkwo', 'teacher@greenfield.test', null, [[$greenfield, SchoolRole::Teacher]]],
            ['Mallam Sani Abubakar', 'sani@teachers.test', null, [[$greenfield, SchoolRole::Teacher], [$alNoor, SchoolRole::Teacher]]],
            ['Hajiya Amina Bello', null, '+2348021234567', [[$greenfield, SchoolRole::Parent]]],
            ['Ustadh Ibrahim Musa', 'admin@alnoor.test', null, [[$alNoor, SchoolRole::SchoolAdmin]]],
        ];

        foreach ($people as [$name, $email, $phone, $memberships]) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'email_verified_at' => $email ? now() : null,
            ]);

            foreach ($memberships as [$school, $role]) {
                $addMember->handle($school, $user, $role);
            }
        }

        DemoRequest::factory()->count(4)->create();

        $this->command?->table(['Who', 'Log in with', 'Password'], [
            ['Platform admin', 'admin@taaleem.test', 'password'],
            ['Greenfield proprietor (school admin)', 'proprietor@greenfield.test', 'password'],
            ['Greenfield principal', 'principal@greenfield.test', 'password'],
            ['Greenfield bursar', 'bursar@greenfield.test', 'password'],
            ['Teacher in two schools', 'sani@teachers.test', 'password'],
            ['Parent (phone login)', '0802 123 4567', 'password'],
            ['Al-Noor school admin', 'admin@alnoor.test', 'password'],
        ]);
    }
}
