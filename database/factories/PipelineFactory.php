<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'entity_type_id' => EntityType::factory(),
            'name' => fake()->words(3, true),
            'pipeline_version' => 1,
            'pipeline_updated_at' => now(),
        ];
    }

    public function withEntityType(EntityType $entityType): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type_id' => $entityType->id,
            'attribute_id' => Attribute::factory()->create(['entity_type_id' => $entityType->id])->id,
        ]);
    }
}
