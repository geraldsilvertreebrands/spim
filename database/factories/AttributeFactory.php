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

        // Generate valid attribute configuration that passes validation rules
        $isSync = $this->faker->randomElement(['no', 'from_external', 'to_external']);
        $needsApproval = $this->faker->randomElement(['yes', 'only_low_confidence', 'no']);
        $editable = $this->faker->randomElement(['yes', 'no', 'overridable']);

        // Enforce validation rules to prevent conflicts
        if ($isSync === 'from_external') {
            // Attributes from external cannot be editable/overridable or need approval
            $editable = 'no';
            $needsApproval = 'no';
        }

        return [
            'entity_type_id' => $entityTypeId,
            'name' => Str::slug($this->faker->unique()->words(2, true), '_'),
            'data_type' => $dataType,
            'editable' => $editable,
            'is_pipeline' => 'no',
            'is_sync' => $isSync,
            'needs_approval' => $needsApproval,
            'allowed_values' => $allowedValues,
            'linked_entity_type_id' => $linkedTypeId,
            'ui_class' => null,
        ];
    }
}
