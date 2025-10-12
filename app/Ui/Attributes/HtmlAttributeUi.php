<?php

namespace App\Ui\Attributes;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use App\Models\Entity;
use App\Services\AttributeService;
use App\Services\EavWriter;
use Illuminate\Support\Str;

class HtmlAttributeUi implements AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        return Str::limit(strip_tags((string) $entity->getAttr($attribute->name, $mode, '')), 80);
    }

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string
    {
        return (string) $entity->getAttr($attribute->name, $mode, '');
    }

    public function form(Entity $entity, Attribute $attribute): array
    {
        return [
            'name' => $attribute->name,
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
