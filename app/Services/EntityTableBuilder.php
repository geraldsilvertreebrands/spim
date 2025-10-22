<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\UserPreference;
use App\Support\AttributeUiRegistry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class EntityTableBuilder
{
    public function __construct(
        protected AttributeUiRegistry $registry
    ) {}

    /**
     * Build table columns for an entity type.
     */
    public function buildColumns(EntityType $entityType, ?array $selectedAttributes = null): array
    {
        $columns = [
            TextColumn::make('entity_id')
                ->label('ID')
                ->searchable()
                ->sortable(),
        ];

        // Get user preferences or defaults
        if ($selectedAttributes === null) {
            $selectedAttributes = $this->getSelectedAttributes($entityType);
        }

        if (empty($selectedAttributes)) {
            return $columns;
        }

        // Get all attributes and index by name
        $attributes = Attribute::where('entity_type_id', $entityType->id)
            ->whereIn('name', $selectedAttributes)
            ->get()
            ->keyBy('name');

        // Build columns in the order specified by user preferences
        foreach ($selectedAttributes as $attributeName) {
            if (isset($attributes[$attributeName])) {
                $column = $this->buildColumn($attributes[$attributeName]);
                if ($column) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * Build a single table column for an attribute.
     */
    public function buildColumn(Attribute $attribute): ?TextColumn
    {
        return TextColumn::make($attribute->name)
            ->label($attribute->display_name ?? ucfirst(str_replace('_', ' ', $attribute->name)))
            ->getStateUsing(function (Entity $record) use ($attribute) {
                // Get the formatted value directly, not the raw array
                // This prevents TextColumn from treating arrays as lists to iterate over
                try {
                    $ui = $this->registry->resolve($attribute);
                    return $ui->summarise($record, $attribute);
                } catch (\Exception $e) {
                    // Fallback: get the raw value
                    return $record->getAttr($attribute->name) ?? '';
                }
            })
            ->searchable(query: function ($query, string $search) use ($attribute) {
                return $this->applySearch($query, $attribute, $search);
            })
            ->sortable(query: function ($query, string $direction) use ($attribute) {
                return $this->applySort($query, $attribute, $direction);
            })
            ->limit(50)
            ->wrap()
            ->tooltip(function (Entity $record) use ($attribute): ?string {
                // Show full value in tooltip when hovering over truncated fields
                try {
                    $ui = $this->registry->resolve($attribute);
                    $fullValue = $ui->summarise($record, $attribute);

                    // Only show tooltip if value is long enough to be truncated
                    if (strlen($fullValue) > 50) {
                        return $fullValue;
                    }

                    return null;
                } catch (\Exception $e) {
                    $value = $record->getAttr($attribute->name);

                    if (is_array($value) || is_object($value)) {
                        return json_encode($value, JSON_PRETTY_PRINT);
                    }

                    $strValue = (string) ($value ?? '');
                    return strlen($strValue) > 50 ? $strValue : null;
                }
            });
    }

    /**
     * Apply search query based on attribute data type.
     */
    public function applySearch($query, Attribute $attribute, string $search)
    {
        switch ($attribute->data_type) {
            case 'integer':
                // For integers, try exact match or numeric comparison
                if (is_numeric($search)) {
                    return $query->whereAttr($attribute->name, '=', $search);
                }
                // If not numeric, no results
                return $query->whereRaw('1 = 0');

            case 'select':
                // Search by both key and label
                $allowedValues = $attribute->allowedValues();
                $matchingKeys = [];

                foreach ($allowedValues as $key => $label) {
                    if (stripos($key, $search) !== false || stripos($label, $search) !== false) {
                        $matchingKeys[] = $key;
                    }
                }

                if (empty($matchingKeys)) {
                    return $query->whereRaw('1 = 0');
                }

                // Use whereIn with whereAttr
                return $query->where(function ($q) use ($attribute, $matchingKeys) {
                    foreach ($matchingKeys as $key) {
                        $q->orWhereAttr($attribute->name, '=', $key);
                    }
                });

            case 'multiselect':
                // Similar to select, but stored as comma-separated or JSON
                $allowedValues = $attribute->allowedValues();
                $matchingKeys = [];

                foreach ($allowedValues as $key => $label) {
                    if (stripos($key, $search) !== false || stripos($label, $search) !== false) {
                        $matchingKeys[] = $key;
                    }
                }

                if (empty($matchingKeys)) {
                    return $query->whereRaw('1 = 0');
                }

                // Search for any of the matching keys in the stored value
                return $query->where(function ($q) use ($attribute, $matchingKeys) {
                    foreach ($matchingKeys as $key) {
                        $q->orWhereAttr($attribute->name, 'LIKE', "%{$key}%");
                    }
                });

            case 'text':
            case 'html':
            case 'json':
            default:
                // Text search using LIKE
                return $query->whereAttr($attribute->name, 'LIKE', "%{$search}%");
        }
    }

    /**
     * Apply sort based on attribute data type.
     */
    public function applySort($query, Attribute $attribute, string $direction)
    {
        // Sorting works the same for all types - the database handles it correctly
        // Integer columns sort numerically, text sorts alphabetically, etc.
        return $query->orderByAttr($attribute->name, $direction);
    }

    /**
     * Get selected attributes from user preferences or defaults.
     */
    protected function getSelectedAttributes(EntityType $entityType): array
    {
        $preferenceKey = "entity_type_{$entityType->id}_columns";
        $userId = Auth::id();

        if ($userId) {
            $prefs = UserPreference::get($userId, $preferenceKey);
            if ($prefs !== null) {
                return $prefs;
            }
        }

        return $this->getDefaultAttributes($entityType);
    }

    /**
     * Get default attributes to display (first 5).
     */
    protected function getDefaultAttributes(EntityType $entityType): array
    {
        return Attribute::where('entity_type_id', $entityType->id)
            ->limit(5)
            ->pluck('name')
            ->toArray();
    }
}

