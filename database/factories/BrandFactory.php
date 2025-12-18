<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'company_id' => $this->faker->randomElement([3, 5, 9]), // FtN, PH, UCOOK
            'access_level' => $this->faker->randomElement(['basic', 'premium']),
            'synced_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the brand has basic access level.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'basic',
        ]);
    }

    /**
     * Indicate that the brand has premium access level.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'premium',
        ]);
    }

    /**
     * Set the brand for a specific company.
     */
    public function forCompany(int $companyId): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $companyId,
        ]);
    }

    /**
     * Indicate the brand has been synced recently.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'synced_at' => now(),
        ]);
    }

    /**
     * Indicate the brand has never been synced.
     */
    public function notSynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'synced_at' => null,
        ]);
    }
}
