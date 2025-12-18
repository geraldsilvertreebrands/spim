<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\SyncResult;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class SyncResultFactory extends Factory
{
    protected $model = SyncResult::class;

    public function definition(): array
    {
        return [
            'sync_run_id' => SyncRun::factory(),
            'entity_id' => Entity::factory(),
            'attribute_id' => Attribute::factory(),
            'item_identifier' => $this->faker->word(),
            'status' => $this->faker->randomElement(['success', 'error', 'warning']),
            'error_message' => null,
            'created_at' => now(),
        ];
    }

    public function withError(?string $message = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error_message' => $message ?? $this->faker->sentence(),
        ]);
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'error_message' => null,
        ]);
    }
}
