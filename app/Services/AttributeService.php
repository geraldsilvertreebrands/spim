<?php

namespace App\Services;

use App\Models\Attribute;
use App\Support\AttributeCaster;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttributeService
{
    public function findByName(int $entityTypeId, string $name): Attribute
    {
        $attr = Attribute::query()
            ->where('entity_type_id', $entityTypeId)
            ->where('name', $name)
            ->first();

        if (!$attr) {
            throw new InvalidArgumentException("Attribute {$name} not found for entity type {$entityTypeId}");
        }

        return $attr;
    }

    public function validateValue(Attribute $attribute, $value): void
    {
        if (in_array($attribute->data_type, ['select','multiselect'], true)) {
            $allowed = $attribute->allowedValues();
            $values = $attribute->data_type === 'multiselect' ? (array) $value : [$value];
            foreach ($values as $val) {
                if (!array_key_exists((string) $val, $allowed)) {
                    throw new InvalidArgumentException("Value {$val} is not allowed for attribute {$attribute->name}");
                }
            }
        }

        if (in_array($attribute->data_type, ['belongs_to','belongs_to_multi'], true)) {
            $targetType = $attribute->linkedEntityType;
            if (!$targetType) {
                throw new InvalidArgumentException("Attribute {$attribute->name} has no linked entity type defined");
            }
            $values = $attribute->data_type === 'belongs_to_multi' ? (array) $value : [$value];
            $count = DB::table('entities')
                ->whereIn('id', $values)
                ->where('entity_type_id', $targetType->id)
                ->count();
            if ($count !== count(array_filter($values))) {
                throw new InvalidArgumentException("One or more related entity IDs are invalid for attribute {$attribute->name}");
            }
        }
    }

    public function coerceIn(Attribute $attribute, $value): ?string
    {
        return AttributeCaster::castIn($attribute->data_type, $value);
    }

    public function coerceOut(Attribute $attribute, $value)
    {
        return AttributeCaster::castOut($attribute->data_type, $value);
    }
}
