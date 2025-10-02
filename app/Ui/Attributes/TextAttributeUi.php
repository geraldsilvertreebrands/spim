<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;
use App\Services\AttributeService;
use App\Services\EavWriter;

class TextAttributeUi implements AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        return (string) $entity->getAttr($attribute->name, $mode, '');
    }

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        return (string) $entity->getAttr($attribute->name, $mode, '');
    }

    public function form(Entity $entity, Attribute $attribute): array
    {
        return [
            'type' => 'text',
            'name' => $attribute->name,
            'value' => (string) $entity->getAttr($attribute->name, 'override', ''),
        ];
    }

    public function save(Entity $entity, Attribute $attribute, $input): void
    {
        $value = (string) ($input['value'] ?? '');
        app(AttributeService::class)->validateValue($attribute, $value);
        $encoded = app(AttributeService::class)->coerceIn($attribute, $value);

        if ($attribute->attribute_type === 'versioned') {
            app(EavWriter::class)->upsertVersioned($entity->id, $attribute->id, $encoded);
        } elseif ($attribute->attribute_type === 'input') {
            app(EavWriter::class)->upsertInput($entity->id, $attribute->id, $encoded, 'ui');
        }
    }
}
