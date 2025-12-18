<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;

class IntegerAttributeUi implements AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        return (string) $entity->getAttr($attribute->name, $mode, 0);
    }

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        return (string) $entity->getAttr($attribute->name, $mode, 0);
    }

    public function form(Entity $entity, Attribute $attribute): array
    {
        return [
            'name' => $attribute->name,
            'value' => (int) $entity->getAttr($attribute->name, 'override', 0),
        ];
    }

    public function save(Entity $entity, Attribute $attribute, $input): void
    {
        $value = (int) ($input['value'] ?? 0);
        // Entity setAttribute handles validation and editable mode logic
        $entity->{$attribute->name} = $value;
    }
}
