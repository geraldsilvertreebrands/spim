<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;
use App\Services\AttributeService;
use InvalidArgumentException;

class BelongsToAttributeUi implements AttributeUi
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
            'value' => $entity->getRelated($attribute->name)[0] ?? null,
        ];
    }

    public function save(Entity $entity, Attribute $attribute, $input): void
    {
        $value = $input['value'] ?? null;
        if (! $value) {
            throw new InvalidArgumentException('Related entity ID required');
        }
        app(AttributeService::class)->validateValue($attribute, $value);
        $entity->setRelated($attribute->name, [$value]);
    }
}
