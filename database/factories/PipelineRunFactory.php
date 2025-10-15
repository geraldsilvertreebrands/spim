<?php

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\PipelineRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineRunFactory extends Factory
{
    protected $model = PipelineRun::class;

    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'pipeline_version' => 1,
            'triggered_by' => fake()->randomElement(['schedule', 'entity_save', 'manual']),
            'trigger_reference' => null,
            'status' => 'completed',
            'batch_size' => 200,
            'entities_processed' => fake()->numberBetween(0, 200),
            'entities_failed' => 0,
            'entities_skipped' => 0,
            'tokens_in' => fake()->numberBetween(100, 1000),
            'tokens_out' => fake()->numberBetween(50, 500),
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }
}

