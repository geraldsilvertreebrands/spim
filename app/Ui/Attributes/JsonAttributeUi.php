<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;
use App\Services\AttributeService;
use App\Services\EavWriter;

class JsonAttributeUi implements AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $value = $entity->getAttr($attribute->name, $mode, []);
        return json_encode($value, JSON_PRETTY_PRINT);
    }

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $value = $entity->getAttr($attribute->name, $mode, []);
        return json_encode($value, JSON_PRETTY_PRINT);
    }

    public function form(Entity $entity, Attribute $attribute): array
    {
        return [
            'name' => $attribute->name,
            'value' => json_encode($entity->getAttr($attribute->name, 'override', []), JSON_PRETTY_PRINT),
        ];
    }

    public function save(Entity $entity, Attribute $attribute, $input): void
    {
        $raw = $input['value'] ?? '';
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON supplied');
        }
        // Entity setAttribute handles validation and editable mode logic
        $entity->{$attribute->name} = $decoded;
    }
}
