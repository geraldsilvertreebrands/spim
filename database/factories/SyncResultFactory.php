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
        $syncRunId = SyncRun::query()->inRandomOrder()->value('id') ?? SyncRun::factory()->create()->id;
        $hasEntity = $this->faker->boolean(70);
        $hasAttribute = !$hasEntity || $this->faker->boolean(30);

        return [
            'sync_run_id' => $syncRunId,
            'entity_id' => $hasEntity ? Entity::factory() : null,
            'attribute_id' => $hasAttribute ? Attribute::factory() : null,
            'item_identifier' => $this->faker->bothify('ITEM-####'),
            'operation' => $this->faker->randomElement(['create', 'update', 'delete', 'skip']),
            'status' => $this->faker->randomElement(['success', 'error', 'warning']),
            'error_message' => $this->faker->boolean(30) ? $this->faker->sentence() : null,
            'details' => $this->faker->boolean(50) ? [
                'old_value' => $this->faker->word(),
                'new_value' => $this->faker->word(),
            ] : null,
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error_message' => $this->faker->sentence(),
        ]);
    }

    public function forEntity(Entity $entity): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_id' => $entity->id,
            'item_identifier' => $entity->entity_id,
        ]);
    }

    public function forAttribute(Attribute $attribute): static
    {
        return $this->state(fn (array $attributes) => [
            'attribute_id' => $attribute->id,
            'item_identifier' => $attribute->name,
        ]);
    }

    public function forSyncRun(SyncRun $syncRun): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_run_id' => $syncRun->id,
        ]);
    }
}



