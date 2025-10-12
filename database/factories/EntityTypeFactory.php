<?php

namespace Database\Factories;

use App\Models\EntityType;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityTypeFactory extends Factory
{
    protected $model = EntityType::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        return [
            'name' => $name,
            'display_name' => ucfirst($name),
            'description' => $this->faker->sentence(),
        ];
    }
}
