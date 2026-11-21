<?php

namespace Database\Seeders;

use App\Actions\Platform\SeedSchoolRoles;
use App\Actions\Platform\SyncPermissions;
use App\Enums\DemoRequestKind;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Enums\SchoolType;
use App\Models\DemoRequest;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Database\Seeders\Demo\DemoSchoolBuilder;
use Database\Seeders\Demo\NigerianPeople;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Support\ActivityLogStatus;

/**
 * Demo data for local development: three schools, a platform admin, staff,
 * ~340 students and their parents. Every account's password is "password".
 *
 * Seeding is deterministic (fixed Faker seed), so the same names come back every time.
 */
class DatabaseSeeder extends Seeder
{
    private const CURRENT_YEAR = 2026;

    public function run(SyncPermissions $syncPermissions, SeedSchoolRoles $seedSchoolRoles, ActivityLogStatus $activityLog): void
    {
        // Demo only: cheap hashes so ~700 accounts seed in seconds. Real logins rehash at full cost.
        Hash::setRounds(4);
        $activityLog->disable();

        $faker = Faker::create('en_NG');
        $faker->seed(2026);
        $people = new NigerianPeople($faker);

        $syncPermissions->handle();

        User::factory()->platformAdmin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@taaleem.test',
            'phone' => '+2348000000001',
        ]);

        $greenfield = $this->greenfieldAcademy($people, $seedSchoolRoles);
        $alNoor = $this->alNoorModelSchool($people, $seedSchoolRoles);
        $this->royalCrestCollege($people, $seedSchoolRoles);
        $this->leads();

        $activityLog->enable();
        $this->recentActivity($greenfield, $alNoor);

        $this->printLogins($greenfield, $alNoor);
    }

    private function greenfieldAcademy(NigerianPeople $people, SeedSchoolRoles $seedSchoolRoles): School
    {
        $school = School::factory()->create([
            'name' => 'Greenfield Academy',
            'slug' => 'greenfield',
            'short_code' => 'GFA',
            'school_type' => SchoolType::Combined,
            'state' => 'FCT',
            'lga' => 'Abuja Municipal',
            'address' => '12 Aminu Kano Crescent, Wuse 2, Abuja',
            'phone' => '+2348031234567',
            'email' => 'info@greenfield.test',
            'website' => 'https://greenfield.test',
            'motto' => 'Knowledge and character',
            'status' => SchoolStatus::Active,
        ]);
        $seedSchoolRoles->handle($school);

        $builder = new DemoSchoolBuilder($school, $people, 'Abuja', ['yoruba' => 30, 'igbo' => 30, 'hausa' => 25, 'south' => 15], 'greenfield.test');

        $builder->build(function (DemoSchoolBuilder $b) use ($people): void {
            $b->classes([
                'Primary 1' => ['B'], 'Primary 2' => ['B'], 'Primary 3' => ['B'], 'Primary 4' => ['B'], 'Primary 5' => ['B'], 'Primary 6' => ['B'],
                'JSS 1' => ['B'], 'JSS 2' => ['B'], 'JSS 3' => ['B'],
            ]);
            $b->sessions(self::CURRENT_YEAR - 1, self::CURRENT_YEAR);

            // Leadership and office
            $b->staff('proprietor', ['title' => 'Dr.', 'first' => 'Ngozi', 'last' => 'Okafor', 'gender' => 'female', 'email' => 'proprietor@greenfield.test', 'designation' => 'Proprietor', 'department' => 'Management', 'qualification' => 'PhD Educational Management', 'joined' => '2009-09-01'], SchoolRole::SchoolAdmin);
            $b->staff('principal', ['title' => 'Mrs.', 'first' => 'Funke', 'last' => 'Adebayo', 'gender' => 'female', 'email' => 'principal@greenfield.test', 'designation' => 'Principal', 'department' => 'Management', 'qualification' => 'M.Ed Educational Administration'], SchoolRole::Principal);
            $b->staff('vice-principal', ['title' => 'Mr.', 'first' => 'Ibrahim', 'last' => 'Danjuma', 'gender' => 'male', 'email' => 'vp@greenfield.test', 'designation' => 'Vice Principal (Academics)', 'department' => 'Management'], SchoolRole::Principal);
            $b->staff('head-teacher', ['title' => 'Mrs.', 'first' => 'Chioma', 'last' => 'Eze', 'gender' => 'female', 'email' => 'headteacher@greenfield.test', 'designation' => 'Head Teacher (Nursery & Primary)', 'department' => 'Nursery & Primary'], SchoolRole::Principal);
            $b->staff('bursar', ['title' => 'Mrs.', 'first' => 'Grace', 'last' => 'Udo', 'gender' => 'female', 'email' => 'bursar@greenfield.test', 'designation' => 'Bursar', 'department' => 'Accounts', 'qualification' => 'B.Sc Accounting, ACA'], SchoolRole::Bursar);
            $b->staff('admin-officer', ['title' => 'Mr.', 'first' => 'Kelechi', 'last' => 'Ibe', 'gender' => 'male', 'email' => 'office@greenfield.test', 'designation' => 'Admin Officer', 'department' => 'Administration', 'qualification' => 'HND Office Technology'], SchoolRole::SchoolAdmin);
            $b->staff('store', ['title' => 'Mr.', 'first' => 'Tunde', 'last' => 'Bakare', 'gender' => 'male', 'email' => 'store@greenfield.test', 'designation' => 'Store Keeper', 'department' => 'Administration', 'qualification' => 'OND Business Administration'], SchoolRole::StoreKeeper);

            // Secondary subject specialists
            $specialistStaff = [
                'okonkwo' => ['Mr.', 'Emeka', 'Okonkwo', 'male', 'Mathematics Teacher', 'Sciences', 'teacher@greenfield.test'],
                'ogunleye' => ['Mrs.', 'Bisola', 'Ogunleye', 'female', 'Mathematics Teacher', 'Sciences', null],
                'nwachukwu' => ['Mrs.', 'Ifeoma', 'Nwachukwu', 'female', 'English Language Teacher', 'Languages', null],
                'akpan' => ['Mr.', 'David', 'Akpan', 'male', 'English & Literature Teacher', 'Languages', null],
                'garba' => ['Mrs.', 'Hauwa', 'Garba', 'female', 'Biology Teacher', 'Sciences', null],
                'ajayi' => ['Mr.', 'Kunle', 'Ajayi', 'male', 'Physics Teacher', 'Sciences', null],
                'uzor' => ['Mr.', 'Obinna', 'Uzor', 'male', 'Chemistry Teacher', 'Sciences', null],
                'adeyemi' => ['Mrs.', 'Temitope', 'Adeyemi', 'female', 'Economics Teacher', 'Commercial', null],
                'lawal' => ['Mr.', 'Usman', 'Lawal', 'male', 'Government Teacher', 'Social Sciences', null],
                'bassey' => ['Mrs.', 'Esther', 'Bassey', 'female', 'Geography Teacher', 'Social Sciences', null],
                'okon' => ['Mr.', 'Samuel', 'Okon', 'male', 'Christian Religious Studies Teacher', 'Religious Studies', null],
                'sani' => ['Mallam', 'Sani', 'Abubakar', 'male', 'Islamic Studies Teacher', 'Religious Studies', 'sani@teachers.test'],
                'gyang' => ['Madame', 'Deborah', 'Gyang', 'female', 'French Teacher', 'Languages', null],
                'fashola' => ['Mr.', 'Tobi', 'Fashola', 'male', 'Computer Studies Teacher', 'Sciences', null],
                'shehu' => ['Malama', 'Rukayya', 'Shehu', 'female', 'Hausa Teacher', 'Languages', null],
                'ekpo' => ['Mr.', 'Victor', 'Ekpo', 'male', 'PHE & Creative Arts Teacher', 'Vocational', null],
            ];

            foreach ($specialistStaff as $key => [$title, $first, $last, $gender, $designation, $department, $email]) {
                $b->staff($key, array_filter(compact('title', 'first', 'last', 'gender', 'designation', 'department', 'email')), SchoolRole::Teacher);
            }

            $s = fn (string $key) => $b->staffMember($key);
            $b->assignTeachers([
                'MTH' => ['junior' => $s('okonkwo'), 'senior' => $s('ogunleye')],
                'FMT' => ['senior' => $s('okonkwo')],
                'ENG' => ['junior' => $s('nwachukwu'), 'senior' => $s('akpan')],
                'LIT' => ['senior' => $s('akpan')],
                'BSC' => ['junior' => $s('garba')],
                'BIO' => ['senior' => $s('garba')],
                'PHY' => ['senior' => $s('ajayi')],
                'BTE' => ['junior' => $s('ajayi')],
                'CHM' => ['senior' => $s('uzor')],
                'AGR' => ['junior' => $s('uzor'), 'senior' => $s('uzor')],
                'ECO' => ['senior' => $s('adeyemi')],
                'COM' => ['senior' => $s('adeyemi')],
                'ACC' => ['senior' => $s('adeyemi')],
                'BUS' => ['junior' => $s('adeyemi')],
                'GOV' => ['senior' => $s('lawal')],
                'CVE' => ['junior' => $s('lawal'), 'senior' => $s('lawal')],
                'SOS' => ['junior' => $s('lawal')],
                'GEO' => ['senior' => $s('bassey')],
                'HEC' => ['junior' => $s('bassey')],
                'CRS' => ['primary' => $s('okon'), 'junior' => $s('okon'), 'senior' => $s('okon')],
                'IRS' => ['primary' => $s('sani'), 'junior' => $s('sani'), 'senior' => $s('sani')],
                'FRE' => ['primary' => $s('gyang'), 'junior' => $s('gyang')],
                'CPS' => ['primary' => $s('fashola'), 'junior' => $s('fashola')],
                'DAT' => ['senior' => $s('fashola')],
                'HAU' => ['primary' => $s('shehu'), 'junior' => $s('shehu')],
                'PHE' => ['junior' => $s('ekpo')],
                'CCA' => ['junior' => $s('ekpo')],
            ], $b->classTeachers());

            $b->formTeachers([
                'JSS 1|A' => 'nwachukwu', 'JSS 1|B' => 'garba', 'JSS 2|A' => 'okonkwo', 'JSS 2|B' => 'ekpo', 'JSS 3|A' => 'lawal', 'JSS 3|B' => 'okon',
                'SSS 1|A' => 'ogunleye', 'SSS 1|B' => 'akpan', 'SSS 1|C' => 'adeyemi',
                'SSS 2|A' => 'ajayi', 'SSS 2|B' => 'bassey', 'SSS 2|C' => 'sani',
                'SSS 3|A' => 'uzor', 'SSS 3|B' => 'gyang', 'SSS 3|C' => 'fashola',
            ]);

            // Named families for the demo walkthrough
            $b->namedFamily(
                ['title' => 'Hajiya', 'first_name' => 'Amina', 'last_name' => 'Bello', 'relationship' => 'mother', 'phone' => '+2348021234567', 'email' => 'amina.bello@family.test', 'occupation' => 'Pharmacist'],
                ['title' => 'Alhaji', 'first_name' => 'Musa', 'last_name' => 'Bello', 'relationship' => 'father', 'phone' => '+2348037654321', 'email' => null, 'occupation' => 'Civil engineer'],
                'hausa', 'Islam', 'Kano',
            );
            $b->namedFamily(
                ['title' => 'Engr.', 'first_name' => 'Ikenna', 'last_name' => 'Nwosu', 'relationship' => 'father', 'phone' => '+2348033334444', 'email' => 'ikenna.nwosu@family.test', 'occupation' => 'Engineer'],
                ['title' => 'Mrs.', 'first_name' => 'Adaeze', 'last_name' => 'Nwosu', 'relationship' => 'mother', 'phone' => '+2348055556666', 'email' => null, 'occupation' => 'Banker'],
                'igbo', 'Christianity', 'Anambra',
            );

            // The students from the landing page's broadsheet, in JSS 2A
            $landingFamilies = [
                'okafor' => ['Mr.', 'Chukwudi', 'Okafor', 'father', 'igbo', 'Christianity', 'Anambra'],
                'adeyemi' => ['Mrs.', 'Yetunde', 'Adeyemi', 'mother', 'yoruba', 'Christianity', 'Oyo'],
                'eze' => ['Dr.', 'Uchenna', 'Eze', 'father', 'igbo', 'Christianity', 'Enugu'],
                'ibrahim' => ['Alhaji', 'Yusuf', 'Ibrahim', 'father', 'hausa', 'Islam', 'Katsina'],
            ];
            $landingPhones = [];

            foreach ($landingFamilies as $key => [$title, $first, $last, $relationship, $heritage, $religion, $state]) {
                $landingPhones[$key] = $people->phone();
                $b->namedFamily(
                    ['title' => $title, 'first_name' => $first, 'last_name' => $last, 'relationship' => $relationship, 'phone' => $landingPhones[$key], 'email' => strtolower($first.'.'.$last).'@family.test', 'occupation' => $people->occupation()],
                    null, $heritage, $religion, $state,
                );
            }

            $b->students(
                ['nursery' => 8, 'primary' => 10, 'junior' => 12, 'senior' => 8],
                [
                    ['level' => 'JSS 2', 'arm' => 'A', 'family' => '+2348021234567', 'student' => ['first_name' => 'Aisha', 'middle_name' => 'Zainab', 'gender' => 'female', 'date_of_birth' => '2013-03-14', 'admitted_on' => '2024-09-09', 'genotype' => 'AA', 'blood_group' => 'O+', 'medical_notes' => null]],
                    ['level' => 'Primary 4', 'arm' => 'A', 'family' => '+2348021234567', 'student' => ['first_name' => 'Fatima', 'middle_name' => null, 'gender' => 'female', 'date_of_birth' => '2017-06-02', 'admitted_on' => '2022-09-12', 'genotype' => 'AS', 'medical_notes' => null]],
                    ['level' => 'SSS 1', 'arm' => 'A', 'family' => '+2348021234567', 'student' => ['first_name' => 'Abubakar', 'middle_name' => 'Sadiq', 'gender' => 'male', 'date_of_birth' => '2011-01-21', 'admitted_on' => '2021-09-13', 'genotype' => 'AA', 'medical_notes' => 'Allergic to penicillin.']],
                    ['level' => 'Primary 1', 'arm' => 'B', 'family' => '+2348033334444', 'student' => ['first_name' => 'Chidera', 'middle_name' => 'Grace', 'gender' => 'female', 'date_of_birth' => '2020-05-10', 'admitted_on' => '2024-09-09', 'medical_notes' => null]],
                    ['level' => 'JSS 3', 'arm' => 'B', 'family' => '+2348033334444', 'student' => ['first_name' => 'Somtochukwu', 'middle_name' => 'Daniel', 'gender' => 'male', 'date_of_birth' => '2012-02-18', 'admitted_on' => '2021-09-13', 'medical_notes' => null]],
                    ['level' => 'JSS 2', 'arm' => 'A', 'family' => $landingPhones['okafor'], 'student' => ['first_name' => 'Chinedu', 'gender' => 'male', 'admitted_on' => '2025-09-08']],
                    ['level' => 'JSS 2', 'arm' => 'A', 'family' => $landingPhones['adeyemi'], 'student' => ['first_name' => 'Tunde', 'gender' => 'male', 'admitted_on' => '2025-09-08']],
                    ['level' => 'JSS 2', 'arm' => 'A', 'family' => $landingPhones['eze'], 'student' => ['first_name' => 'Ngozi', 'gender' => 'female', 'admitted_on' => '2025-09-08']],
                    ['level' => 'JSS 2', 'arm' => 'A', 'family' => $landingPhones['ibrahim'], 'student' => ['first_name' => 'Musa', 'gender' => 'male', 'admitted_on' => '2025-09-08']],
                ],
            );
            $b->scores();
            $b->reportRemarks();
            $b->attendance();
            $b->fees(['nursery' => 120_000, 'primary' => 145_000, 'junior' => 185_000, 'senior' => 210_000]);
            $b->applicants(
                ['submitted' => 7, 'screening' => 3, 'exam_scheduled' => 6, 'offered' => 4, 'accepted' => 2, 'rejected' => 2, 'withdrawn' => 1],
                ['Nursery 1', 'Primary 1', 'Primary 4', 'JSS 1', 'JSS 1', 'SSS 1'],
            );
        });

        return $school;
    }

    private function alNoorModelSchool(NigerianPeople $people, SeedSchoolRoles $seedSchoolRoles): School
    {
        $school = School::factory()->create([
            'name' => 'Al-Noor Model School',
            'slug' => 'al-noor',
            'short_code' => 'ANM',
            'school_type' => SchoolType::Islamiyya,
            'state' => 'Kano',
            'lga' => 'Nassarawa',
            'address' => '7 Lugard Road, Nassarawa GRA, Kano',
            'phone' => '+2348051112222',
            'email' => 'office@alnoor.test',
            'motto' => 'Knowledge is light',
            'status' => SchoolStatus::Trial,
            'trial_ends_at' => now()->addDays(21),
        ]);
        $seedSchoolRoles->handle($school);

        $builder = new DemoSchoolBuilder($school, $people, 'Kano', ['hausa' => 85, 'yoruba' => 15], 'alnoor.test');

        $builder->build(function (DemoSchoolBuilder $b): void {
            $b->classes();
            $b->sessions(self::CURRENT_YEAR - 1, self::CURRENT_YEAR);

            $b->staff('proprietor', ['title' => 'Ustadh', 'first' => 'Ibrahim', 'last' => 'Musa', 'gender' => 'male', 'email' => 'admin@alnoor.test', 'designation' => 'Proprietor', 'department' => 'Management', 'qualification' => 'B.A Islamic Studies, M.Ed', 'joined' => '2015-01-12'], SchoolRole::SchoolAdmin);
            $b->staff('head', ['title' => 'Malam', 'first' => 'Kabiru', 'last' => 'Yusuf', 'gender' => 'male', 'email' => 'head@alnoor.test', 'designation' => 'Head Teacher', 'department' => 'Management'], SchoolRole::Principal);
            $b->staff('bursar', ['title' => 'Malam', 'first' => 'Aliyu', 'last' => 'Garba', 'gender' => 'male', 'email' => 'bursar@alnoor.test', 'designation' => 'Bursar', 'department' => 'Accounts'], SchoolRole::Bursar);
            $b->staff('sani', ['title' => 'Mallam', 'first' => 'Sani', 'last' => 'Abubakar', 'gender' => 'male', 'email' => 'sani@teachers.test', 'designation' => 'Arabic & Islamic Studies Teacher', 'department' => 'Islamiyya'], SchoolRole::Teacher);
            $b->staff('quran', ['title' => 'Malama', 'first' => 'Hadiza', 'last' => 'Umar', 'gender' => 'female', 'email' => 'hadiza.umar@alnoor.test', 'designation' => 'Qur’an & Tajweed Teacher', 'department' => 'Islamiyya', 'qualification' => 'Diploma in Qur’anic Studies'], SchoolRole::Teacher);

            $b->assignTeachers([
                'ARB' => ['nursery' => $b->staffMember('sani'), 'primary' => $b->staffMember('sani')],
                'IRS' => ['primary' => $b->staffMember('sani')],
                'HDF' => ['primary' => $b->staffMember('sani')],
                'QUR' => ['nursery' => $b->staffMember('quran'), 'primary' => $b->staffMember('quran')],
            ], $b->classTeachers());

            $b->namedFamily(
                ['title' => 'Hajiya', 'first_name' => 'Amina', 'last_name' => 'Bello', 'relationship' => 'mother', 'phone' => '+2348021234567', 'email' => 'amina.bello@family.test', 'occupation' => 'Pharmacist'],
                null, 'hausa', 'Islam', 'Kano',
            );

            $b->students(
                ['nursery' => 7, 'primary' => 7],
                [
                    ['level' => 'Primary 2', 'arm' => 'A', 'family' => '+2348021234567', 'student' => ['first_name' => 'Maryam', 'middle_name' => 'Hafsat', 'gender' => 'female', 'date_of_birth' => '2019-09-09', 'admitted_on' => '2025-09-15', 'medical_notes' => null]],
                ],
            );
            $b->scores();
            $b->reportRemarks();
            $b->attendance();
            $b->fees(['nursery' => 60_000, 'primary' => 75_000, 'islamiyya' => 40_000]);
        });

        return $school;
    }

    /**
     * A suspended school, to see what staff get when a school's account is on hold.
     */
    private function royalCrestCollege(NigerianPeople $people, SeedSchoolRoles $seedSchoolRoles): void
    {
        $school = School::factory()->create([
            'name' => 'Royal Crest College',
            'slug' => 'royal-crest',
            'short_code' => 'RCC',
            'school_type' => SchoolType::Secondary,
            'state' => 'Lagos',
            'address' => '21 Admiralty Way, Lekki Phase 1, Lagos',
            'phone' => '+2348091234567',
            'email' => 'info@royalcrest.test',
            'status' => SchoolStatus::Active,
        ]);
        $seedSchoolRoles->handle($school);

        (new DemoSchoolBuilder($school, $people, 'Lagos', ['yoruba' => 60, 'igbo' => 25, 'south' => 15], 'royalcrest.test'))
            ->build(function (DemoSchoolBuilder $b): void {
                $b->classes();
                $b->sessions(self::CURRENT_YEAR - 1, self::CURRENT_YEAR);
                $b->staff('proprietor', ['title' => 'Chief', 'first' => 'Babatunde', 'last' => 'Olowookere', 'gender' => 'male', 'email' => 'admin@royalcrest.test', 'designation' => 'Proprietor', 'department' => 'Management'], SchoolRole::SchoolAdmin);
                $b->students(['junior' => 4, 'senior' => 3]);
            });

        $school->update(['status' => SchoolStatus::Suspended]);
    }

    private function leads(): void
    {
        $leads = [
            [DemoRequestKind::Demo, 'contact', 'Mrs. Adaobi Chukwu', 'adaobi@brightfuture.test', null, 'Bright Future Schools, Enugu', '400+', 'We have 3 campuses. Can each campus have its own results but one proprietor login?', 2],
            [DemoRequestKind::Trial, 'contact', 'Malam Haruna Sule', 'haruna@nuruliman.test', null, 'Nurul Iman Islamiyya, Zaria', '150–400', 'We run weekday and weekend Islamiyya classes.', 5],
            [DemoRequestKind::Demo, 'hero', null, null, '+2348067778888', null, null, null, 9],
            [DemoRequestKind::Question, 'contact', 'Mr. Femi Ogundipe', 'femi@ogundipeschools.test', null, 'Ogundipe Memorial College', '150–400', 'Do you support Paystack for school fees?', 26],
            [DemoRequestKind::Demo, 'cta', null, 'bursar@stmarys.test', null, null, null, null, 50],
        ];

        foreach ($leads as [$kind, $source, $name, $email, $phone, $schoolName, $students, $message, $hoursAgo]) {
            DemoRequest::query()->create([
                'kind' => $kind, 'source' => $source, 'name' => $name, 'email' => $email, 'phone' => $phone,
                'school_name' => $schoolName, 'student_count' => $students, 'message' => $message,
                'created_at' => now()->subHours($hoursAgo), 'updated_at' => now()->subHours($hoursAgo),
            ]);
        }
    }

    /**
     * A believable recent activity log, so the dashboards and log viewer have something to show.
     */
    private function recentActivity(School $greenfield, School $alNoor): void
    {
        $entries = [
            [$greenfield, 'proprietor@greenfield.test', 'auth', 'signed in to the school', '102.89.34.12', 26],
            [$greenfield, 'principal@greenfield.test', 'auth', 'signed in to the school', '105.112.18.200', 20],
            [$greenfield, 'principal@greenfield.test', 'academics', 'changed the dates of First Term', '105.112.18.200', 19],
            [$greenfield, 'office@greenfield.test', 'people', 'enrolled Chinedu Okafor', '102.89.40.7', 8],
            [$greenfield, 'teacher@greenfield.test', 'auth', 'signed in to the school', '197.210.53.90', 3],
            [$greenfield, 'bursar@greenfield.test', 'auth', 'signed in to the school', '102.89.34.55', 1],
            [$alNoor, 'admin@alnoor.test', 'auth', 'signed in to the school', '105.113.7.21', 6],
            [$alNoor, 'admin@alnoor.test', 'academics', 'made First Term 2026/2027 the current term', '105.113.7.21', 6],
        ];

        foreach ($entries as [$school, $email, $log, $description, $ip, $hoursAgo]) {
            app(CurrentSchool::class)->run($school, fn () => activity($log)
                ->causedBy(User::query()->where('email', $email)->firstOrFail())
                ->createdAt(now()->subHours($hoursAgo))
                ->tap(fn ($activity) => $activity->ip_address = $ip)
                ->log($description));
        }
    }

    private function printLogins(School $greenfield, School $alNoor): void
    {
        $admissionNo = fn (School $school, string $first, string $last) => app(CurrentSchool::class)->run($school, fn () => Student::query()
            ->where('first_name', $first)->where('last_name', $last)->value('admission_no'));

        $this->command?->newLine();
        $this->command?->info('Demo logins (password for every account: password)');
        $this->command?->table(['Who', 'Where', 'Log in with'], [
            ['Platform super admin', 'All schools', 'admin@taaleem.test'],
            ['Proprietor (school admin)', 'Greenfield Academy', 'proprietor@greenfield.test'],
            ['Admin officer (school admin)', 'Greenfield Academy', 'office@greenfield.test'],
            ['Principal', 'Greenfield Academy', 'principal@greenfield.test'],
            ['Bursar', 'Greenfield Academy', 'bursar@greenfield.test'],
            ['Teacher, form teacher of JSS 2A', 'Greenfield Academy', 'teacher@greenfield.test'],
            ['Teacher in two schools', 'Greenfield + Al-Noor', 'sani@teachers.test'],
            ['Parent of 3 (+1 at Al-Noor)', 'Greenfield + Al-Noor', '0802 123 4567'],
            ['Parent of 2', 'Greenfield Academy', '0803 333 4444'],
            ['Student, JSS 2A (Aisha Bello)', 'Greenfield Academy', 'school code GFA + '.$admissionNo($greenfield, 'Aisha', 'Bello')],
            ['Student, SSS 1A (Abubakar Bello)', 'Greenfield Academy', 'school code GFA + '.$admissionNo($greenfield, 'Abubakar', 'Bello')],
            ['Student, Primary 2A (Maryam Bello)', 'Al-Noor Model School', 'school code ANM + '.$admissionNo($alNoor, 'Maryam', 'Bello')],
            ['School admin', 'Al-Noor Model School', 'admin@alnoor.test'],
            ['School admin (school suspended)', 'Royal Crest College', 'admin@royalcrest.test'],
        ]);
    }
}
