<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;
use App\Services\AttributeService;
use App\Services\EavWriter;

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
        app(AttributeService::class)->validateValue($attribute, $value);
        $encoded = app(AttributeService::class)->coerceIn($attribute, $value);

        if ($attribute->attribute_type === 'versioned') {
            app(EavWriter::class)->upsertVersioned($entity->id, $attribute->id, $encoded);
        } elseif ($attribute->attribute_type === 'input') {
            app(EavWriter::class)->upsertInput($entity->id, $attribute->id, $encoded, 'ui');
        }
    }
}
