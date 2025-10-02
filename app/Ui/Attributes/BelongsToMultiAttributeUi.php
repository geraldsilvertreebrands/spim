<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;
use App\Services\AttributeService;

class BelongsToMultiAttributeUi implements AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $ids = $entity->getRelated($attribute->name);
        return implode(', ', $ids);
    }

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        $ids = $entity->getRelated($attribute->name);
        return implode("\n", $ids);
    }

    public function form(Entity $entity, Attribute $attribute): array
    {
        return [
            'name' => $attribute->name,
            'value' => $entity->getRelated($attribute->name),
        ];
    }

    public function save(Entity $entity, Attribute $attribute, $input): void
    {
        $value = $input['value'] ?? [];
        if (is_string($value)) {
            $value = [$value];
        }
        app(AttributeService::class)->validateValue($attribute, $value);
        $entity->setRelated($attribute->name, $value);
    }
}
