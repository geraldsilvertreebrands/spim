<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'allowed_values' => 'array',
        'is_synced' => 'boolean',
    ];

    public function entityType()
    {
        return $this->belongsTo(EntityType::class, 'entity_type_id');
    }

    public function linkedEntityType()
    {
        return $this->belongsTo(EntityType::class, 'linked_entity_type_id');
    }

    public function attributeSection()
    {
        return $this->belongsTo(AttributeSection::class, 'attribute_section_id');
    }

    public function allowedValues(): array
    {
        $values = $this->allowed_values ?? [];
        if (array_values($values) === $values) {
            // normalize list into dictionary where key == label
            return collect($values)->mapWithKeys(fn ($value) => [$value => $value])->toArray();
        }
        return $values;
    }

    public function setAllowedValuesAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['allowed_values'] = null;
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value => $value];
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $label) {
                // Check if this is a sequential array (0, 1, 2...) with no explicit keys
                // by checking if the key matches the iteration position
                $isSequentialArray = is_int($key) && $key === count($normalized);

                if ($isSequentialArray) {
                    // No meaningful keys provided, use label as key
                    $normalized[$label] = $label;
                } else {
                    // Preserve all keys (including numeric keys like "1", "2")
                    $normalized[(string) $key] = (string) $label;
                }
            }
            $this->attributes['allowed_values'] = json_encode($normalized);
            return;
        }

        $this->attributes['allowed_values'] = null;
    }
}
