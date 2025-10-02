<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\EntityType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        $typeId = EntityType::query()->inRandomOrder()->value('id') ?? EntityType::factory()->create()->id;

        return [
            'id' => (string) Str::ulid(),
            'entity_type_id' => $typeId,
            'entity_id' => $this->faker->unique()->bothify('sku-####'),
        ];
    }
}
