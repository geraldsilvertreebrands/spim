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
                if (is_int($key)) {
                    $normalized[$label] = $label;
                } else {
                    $normalized[(string) $key] = (string) $label;
                }
            }
            $this->attributes['allowed_values'] = json_encode($normalized);
            return;
        }

        $this->attributes['allowed_values'] = null;
    }
}
