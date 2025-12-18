<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Pipeline;
use App\Models\PipelineEval;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineEvalFactory extends Factory
{
    protected $model = PipelineEval::class;

    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'entity_id' => Entity::factory(),
            'input_hash' => fake()->sha256(),
            'desired_output' => ['value' => fake()->word()],
            'notes' => fake()->sentence(),
            'actual_output' => ['value' => fake()->word()],
            'justification' => fake()->sentence(),
            'confidence' => fake()->randomFloat(2, 0.5, 1.0),
            'last_ran_at' => now(),
        ];
    }

    public function passing(): static
    {
        $output = ['value' => 'test-value'];

        return $this->state(fn (array $attributes) => [
            'desired_output' => $output,
            'actual_output' => $output,
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn (array $attributes) => [
            'desired_output' => ['value' => 'expected'],
            'actual_output' => ['value' => 'different'],
        ]);
    }
}
