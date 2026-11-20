<?php

namespace Database\Factories;

use App\Enums\DemoRequestKind;
use App\Models\DemoRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoRequest>
 */
class DemoRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => fake()->randomElement(DemoRequestKind::cases()),
            'source' => fake()->randomElement(['hero', 'cta', 'contact']),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'school_name' => fake()->lastName().' Academy',
            'student_count' => fake()->randomElement(['Under 50', '50–150', '150–400', '400+']),
            'message' => fake()->optional()->sentence(),
        ];
    }
}
