<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;

class MultiselectAttributeUi implements AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $values = (array) $entity->getAttr($attribute->name, $mode, []);
        $allowed = $attribute->allowedValues();

        return implode(', ', array_map(fn ($val) => $allowed[$val] ?? $val, $values));
    }

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $values = (array) $entity->getAttr($attribute->name, $mode, []);
        $allowed = $attribute->allowedValues();

        return implode(
            "\n",
            array_map(fn ($val) => $allowed[$val] ?? $val, $values)
        );
    }

    public function form(Entity $entity, Attribute $attribute): array
    {
        return [
            'name' => $attribute->name,
            'options' => $attribute->allowedValues(),
            'value' => (array) $entity->getAttr($attribute->name, 'override', []),
        ];
    }

    public function save(Entity $entity, Attribute $attribute, $input): void
    {
        $value = $input['value'] ?? [];
        if (is_string($value)) {
            $value = [$value];
        }
        // Entity setAttribute handles validation and editable mode logic
        $entity->{$attribute->name} = $value;
    }
}
