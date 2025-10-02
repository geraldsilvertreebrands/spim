<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\EntityType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        $entityTypeId = EntityType::query()->inRandomOrder()->value('id') ?? EntityType::factory()->create()->id;
        $dataTypes = ['integer','text','html','json','select','multiselect','belongs_to','belongs_to_multi'];
        $attributeTypes = ['versioned','input','timeseries'];
        $dataType = $this->faker->randomElement($dataTypes);

        $allowedValues = null;
        if (in_array($dataType, ['select','multiselect'], true)) {
            $allowedValues = [
                'RED' => 'Red',
                'BLU' => 'Blue',
            ];
        }

        $linkedTypeId = null;
        if (in_array($dataType, ['belongs_to','belongs_to_multi'], true)) {
            $linkedTypeId = EntityType::factory()->create()->id;
        }

        return [
            'entity_type_id' => $entityTypeId,
            'name' => Str::slug($this->faker->unique()->words(2, true), '_'),
            'data_type' => $dataType,
            'attribute_type' => $this->faker->randomElement($attributeTypes),
            'review_required' => $this->faker->randomElement(['always','low_confidence','no']),
            'allowed_values' => $allowedValues,
            'linked_entity_type_id' => $linkedTypeId,
            'is_synced' => false,
            'ui_class' => null,
        ];
    }
}
