<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;
use App\Services\AttributeService;
use App\Services\EavWriter;

class SelectAttributeUi implements AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $value = (string) $entity->getAttr($attribute->name, $mode, '');
        return $attribute->allowedValues()[$value] ?? $value;
    }

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $value = (string) $entity->getAttr($attribute->name, $mode, '');
        $label = $attribute->allowedValues()[$value] ?? $value;
        return $value === $label ? $label : "{$label} ({$value})";
    }

    public function form(Entity $entity, Attribute $attribute): array
    {
        return [
            'name' => $attribute->name,
            'options' => $attribute->allowedValues(),
            'value' => (string) $entity->getAttr($attribute->name, 'override', ''),
        ];
    }

    public function save(Entity $entity, Attribute $attribute, $input): void
    {
        $value = (string) ($input['value'] ?? '');
        // Entity setAttribute handles validation and editable mode logic
        $entity->{$attribute->name} = $value;
    }
}
