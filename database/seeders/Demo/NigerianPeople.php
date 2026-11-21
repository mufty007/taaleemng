<?php

namespace Database\Seeders\Demo;

use Faker\Generator;

/**
 * Deterministic, realistic Nigerian names, origins and contact details for demo data.
 * First names and surnames are kept within one heritage so "Chinedu Bello" never appears.
 */
final class NigerianPeople
{
    private const HERITAGE = [
        'yoruba' => [
            'male' => ['Tunde', 'Ayodeji', 'Oluwaseun', 'Babatunde', 'Femi', 'Kunle', 'Segun', 'Tobi', 'Damilare', 'Ayomide', 'Olumide', 'Kayode', 'Adewale', 'Gbenga', 'Tolu', 'Ibukun'],
            'female' => ['Funke', 'Yetunde', 'Folake', 'Bisola', 'Temitope', 'Abimbola', 'Omolara', 'Titilayo', 'Ayomide', 'Morenike', 'Adunni', 'Ronke', 'Simisola', 'Tolani', 'Oyinkansola', 'Ifeoluwa'],
            'surnames' => ['Adebayo', 'Ogunleye', 'Balogun', 'Adeyemi', 'Oladipo', 'Akinwale', 'Olatunji', 'Ajayi', 'Adekunle', 'Ogundipe', 'Oyelaran', 'Akande', 'Bamidele', 'Olowookere'],
            'states' => ['Lagos', 'Oyo', 'Ogun', 'Osun', 'Ondo', 'Ekiti', 'Kwara'],
            'muslim_share' => 40,
        ],
        'igbo' => [
            'male' => ['Chinedu', 'Emeka', 'Obinna', 'Chukwuemeka', 'Ikenna', 'Nnamdi', 'Kelechi', 'Uchenna', 'Chidi', 'Somtochukwu', 'Ebuka', 'Chibuike', 'Kenechukwu', 'Ifeanyi', 'Tochukwu'],
            'female' => ['Chioma', 'Adaeze', 'Chiamaka', 'Nneka', 'Ifeoma', 'Amarachi', 'Ugochi', 'Ebere', 'Chidera', 'Kosisochukwu', 'Ogechi', 'Ngozi', 'Chinwe', 'Obiageli', 'Zikora'],
            'surnames' => ['Nwosu', 'Okonkwo', 'Obi', 'Nwachukwu', 'Okeke', 'Chukwu', 'Onyeka', 'Uzor', 'Anyanwu', 'Ibe', 'Okoro', 'Nnadi', 'Ezeani', 'Udeh'],
            'states' => ['Anambra', 'Enugu', 'Imo', 'Abia', 'Ebonyi'],
            'muslim_share' => 0,
        ],
        'hausa' => [
            'male' => ['Musa', 'Ibrahim', 'Abubakar', 'Usman', 'Yusuf', 'Aliyu', 'Bashir', 'Kabiru', 'Abdullahi', 'Umar', 'Sulaiman', 'Nasiru', 'Haruna', 'Ismail', 'Auwal'],
            'female' => ['Aisha', 'Fatima', 'Zainab', 'Hauwa', 'Maryam', 'Amina', 'Hadiza', 'Rukayya', 'Halima', 'Khadija', 'Bilkisu', 'Safiya', 'Nana', 'Asma’u', 'Jamila'],
            'surnames' => ['Abdullahi', 'Mohammed', 'Yusuf', 'Ibrahim', 'Danjuma', 'Lawal', 'Garba', 'Shehu', 'Aliyu', 'Bala', 'Sule', 'Tanko', 'Idris', 'Usman'],
            'states' => ['Kano', 'Kaduna', 'Katsina', 'Sokoto', 'Bauchi', 'Zamfara', 'Niger', 'Jigawa'],
            'muslim_share' => 100,
        ],
        'south' => [
            'male' => ['Edet', 'Effiong', 'Ekpo', 'Godwin', 'Victor', 'Emmanuel', 'Samuel', 'Daniel', 'Joshua', 'Pam', 'Terver', 'Ifiok', 'Ubong', 'Osagie', 'Efe'],
            'female' => ['Blessing', 'Mercy', 'Grace', 'Esther', 'Deborah', 'Joy', 'Precious', 'Faith', 'Idara', 'Nkoyo', 'Ese', 'Mfon', 'Doosuur', 'Ivie', 'Uduak'],
            'surnames' => ['Udo', 'Effiong', 'Bassey', 'Akpan', 'Okon', 'Gyang', 'Ekpo', 'Etim', 'Iorliam', 'Osagie', 'Edet', 'Okpara', 'Tarfa', 'Ikpe'],
            'states' => ['Akwa Ibom', 'Cross River', 'Rivers', 'Plateau', 'Benue', 'Edo', 'Delta', 'Bayelsa'],
            'muslim_share' => 0,
        ],
    ];

    private const OCCUPATIONS = [
        'Civil servant', 'Trader', 'Banker', 'Engineer', 'Medical doctor', 'Nurse', 'Lawyer', 'Accountant',
        'Business owner', 'Lecturer', 'Pharmacist', 'Architect', 'Journalist', 'Army officer', 'Fashion designer',
        'Software developer', 'Contractor', 'Teacher', 'Farmer', 'Clergy',
    ];

    private const PHONE_PREFIXES = ['803', '806', '813', '816', '703', '706', '810', '814', '903', '906', '805', '807', '815', '705', '802', '808', '812', '701', '708', '902', '904', '907'];

    /** @var array<string, true> */
    private array $usedPhones = [];

    public function __construct(private Generator $faker) {}

    /**
     * @param  array<string, int>  $weights  e.g. ['yoruba' => 30, 'igbo' => 30, 'hausa' => 25, 'south' => 15]
     */
    public function heritage(array $weights): string
    {
        $roll = $this->faker->numberBetween(1, array_sum($weights));

        foreach ($weights as $heritage => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $heritage;
            }
        }

        return array_key_first($weights);
    }

    public function firstName(string $heritage, string $gender): string
    {
        return $this->faker->randomElement(self::HERITAGE[$heritage][$gender]);
    }

    public function surname(string $heritage): string
    {
        return $this->faker->randomElement(self::HERITAGE[$heritage]['surnames']);
    }

    public function state(string $heritage): string
    {
        return $this->faker->randomElement(self::HERITAGE[$heritage]['states']);
    }

    public function religion(string $heritage): string
    {
        return $this->faker->numberBetween(1, 100) <= self::HERITAGE[$heritage]['muslim_share'] ? 'Islam' : 'Christianity';
    }

    public function occupation(): string
    {
        return $this->faker->randomElement(self::OCCUPATIONS);
    }

    /**
     * A unique Nigerian mobile number in E.164.
     */
    public function phone(): string
    {
        do {
            $phone = '+234'.$this->faker->randomElement(self::PHONE_PREFIXES).$this->faker->numerify('#######');
        } while (isset($this->usedPhones[$phone]));

        $this->usedPhones[$phone] = true;

        return $phone;
    }

    public function reservePhone(string $phone): void
    {
        $this->usedPhones[$phone] = true;
    }

    /**
     * A title that fits the person: professional titles follow the occupation,
     * Muslim honorifics follow heritage (Hausa "Hajiya", Yoruba "Alhaja").
     */
    public function parentTitle(string $gender, string $religion, string $heritage, string $occupation): string
    {
        $professional = match ($occupation) {
            'Medical doctor', 'Lecturer' => 'Dr.',
            'Engineer' => 'Engr.',
            'Lawyer' => 'Barr.',
            'Architect' => 'Arc.',
            default => null,
        };

        if ($professional !== null && $this->chance(70)) {
            return $professional;
        }

        if ($religion === 'Islam' && $this->chance(55)) {
            return $gender === 'male'
                ? ($heritage === 'hausa' ? $this->faker->randomElement(['Alhaji', 'Mallam']) : 'Alhaji')
                : ($heritage === 'hausa' ? 'Hajiya' : 'Alhaja');
        }

        if ($gender === 'male' && $heritage === 'igbo' && $this->chance(8)) {
            return 'Chief';
        }

        return $gender === 'male' ? 'Mr.' : 'Mrs.';
    }

    public function bloodGroup(): string
    {
        return $this->faker->randomElement(['O+', 'O+', 'O+', 'O+', 'A+', 'A+', 'B+', 'B+', 'AB+', 'O-', 'A-']);
    }

    public function genotype(): string
    {
        $roll = $this->faker->numberBetween(1, 100);

        return match (true) {
            $roll <= 72 => 'AA',
            $roll <= 95 => 'AS',
            $roll <= 99 => 'AC',
            default => 'SS',
        };
    }

    public function address(string $city): string
    {
        $areas = match ($city) {
            'Abuja' => ['Wuse 2', 'Garki', 'Maitama', 'Asokoro', 'Gwarinpa', 'Kubwa', 'Lugbe', 'Jabi', 'Utako', 'Life Camp', 'Lokogoma', 'Apo', 'Katampe', 'Wuye'],
            'Kano' => ['Nassarawa GRA', 'Bompai', 'Tarauni', 'Sabon Gari', 'Hotoro', 'Gyadi-Gyadi', 'Zoo Road', 'Farm Centre', 'Sharada', 'Dorayi'],
            default => ['Ikeja GRA', 'Surulere', 'Yaba', 'Lekki Phase 1', 'Magodo', 'Gbagada', 'Ikoyi', 'Festac'],
        };

        return 'No. '.$this->faker->numberBetween(1, 48).', '.$this->faker->randomElement(['Close', 'Crescent', 'Street', 'Avenue', 'Road']).' '
            .$this->faker->numberBetween(1, 30).', '.$this->faker->randomElement($areas).', '.$city;
    }

    public function chance(int $percent): bool
    {
        return $this->faker->numberBetween(1, 100) <= $percent;
    }

    public function between(int $min, int $max): int
    {
        return $this->faker->numberBetween($min, $max);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $this->faker->randomElement($items);
    }
}
