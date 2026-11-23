<?php

namespace Database\Factories;

use App\Enums\BillingModel;
use App\Enums\ClassStructure;
use App\Enums\Plan;
use App\Enums\SchoolStatus;
use App\Enums\SchoolType;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->lastName().' '.fake()->randomElement(['Academy', 'College', 'Model School', 'International School']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'short_code' => Str::upper(Str::random(3)).fake()->unique()->numberBetween(10, 999),
            'school_type' => fake()->randomElement(SchoolType::cases()),
            'curriculum_type' => 'nigerian',
            'state' => fake()->randomElement(config('nigeria.states')),
            'phone' => '+234'.fake()->numerify('80########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => SchoolStatus::Active,
            'plan' => Plan::Standard,
            'billing_model' => BillingModel::Flat,
            'billing_cycle' => 'termly',
            'class_structure' => ClassStructure::Arms,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SchoolStatus::Trial,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SchoolStatus::Suspended,
        ]);
    }
}
