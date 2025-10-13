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

        // Defaults per new model
        $editable = 'yes';
        $isPipeline = 'no';
        $isSync = 'no';
        $needsApproval = 'no';

        return [
            'entity_type_id' => $entityTypeId,
            'name' => Str::slug($this->faker->unique()->words(2, true), '_'),
            'data_type' => $dataType,
            'editable' => $editable,
            'is_pipeline' => $isPipeline,
            'is_sync' => $isSync,
            'needs_approval' => $needsApproval,
            'allowed_values' => $allowedValues,
            'linked_entity_type_id' => $linkedTypeId,
            'ui_class' => null,
        ];
    }

    public function readonly(): static
    {
        return $this->state(fn (array $attributes) => [
            'editable' => 'no',
        ]);
    }

    public function fromExternal(): static
    {
        return $this->state(fn (array $attributes) => [
            'editable' => 'no',
            'is_sync' => 'from_external',
            'needs_approval' => 'no',
        ]);
    }

    public function toExternal(): static
    {
        return $this->state(fn (array $attributes) => [
            'editable' => 'yes',
            'is_sync' => 'to_external',
            'needs_approval' => 'yes',
        ]);
    }

    public function overridable(): static
    {
        return $this->state(fn (array $attributes) => [
            'editable' => 'overridable',
        ]);
    }
}
