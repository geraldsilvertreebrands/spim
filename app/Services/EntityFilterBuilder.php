<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class EntityFilterBuilder
{
    /**
     * Build filter components for an entity type.
     */
    public function buildFilters(EntityType $entityType, ?array $selectedAttributes = null): array
    {
        $filters = [];

        // Get all attributes or only selected ones
        if ($selectedAttributes === null) {
            $attributes = Attribute::where('entity_type_id', $entityType->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } else {
            $attributes = Attribute::where('entity_type_id', $entityType->id)
                ->whereIn('name', $selectedAttributes)
                ->get()
                ->keyBy('name');

            // Maintain order from selectedAttributes
            $orderedAttributes = [];
            foreach ($selectedAttributes as $name) {
                if (isset($attributes[$name])) {
                    $orderedAttributes[] = $attributes[$name];
                }
            }
            $attributes = collect($orderedAttributes);
        }

        foreach ($attributes as $attribute) {
            $filter = $this->buildFilter($attribute);
            if ($filter) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * Build a single filter for an attribute.
     */
    protected function buildFilter(Attribute $attribute): Filter|SelectFilter|null
    {
        $label = $attribute->display_name ?? ucfirst(str_replace('_', ' ', $attribute->name));

        return match ($attribute->data_type) {
            'select' => $this->buildSelectFilter($attribute, $label),
            'multiselect' => $this->buildMultiselectFilter($attribute, $label),
            'integer' => $this->buildIntegerFilter($attribute, $label),
            'text', 'html' => $this->buildTextFilter($attribute, $label),
            'belongs_to' => $this->buildBelongsToFilter($attribute, $label),
            'belongs_to_multi' => $this->buildBelongsToMultiFilter($attribute, $label),
            'json' => null, // Skip JSON attributes for now
            default => null,
        };
    }

    /**
     * Build a select filter for select attributes.
     */
    protected function buildSelectFilter(Attribute $attribute, string $label): SelectFilter
    {
        return SelectFilter::make($attribute->name)
            ->label($label)
            ->options($attribute->allowedValues())
            ->multiple()
            ->query(function (Builder $query, array $data) use ($attribute) {
                $values = $data['values'] ?? [];
                if (empty($values)) {
                    return $query;
                }

                return $query->where(function (Builder $q) use ($attribute, $values) {
                    foreach ($values as $value) {
                        $q->orWhereAttr($attribute->name, '=', $value);
                    }
                });
            });
    }

    /**
     * Build a filter for multiselect attributes.
     */
    protected function buildMultiselectFilter(Attribute $attribute, string $label): SelectFilter
    {
        return SelectFilter::make($attribute->name)
            ->label($label)
            ->options($attribute->allowedValues())
            ->multiple()
            ->query(function (Builder $query, array $data) use ($attribute) {
                $values = $data['values'] ?? [];
                if (empty($values)) {
                    return $query;
                }

                return $query->where(function (Builder $q) use ($attribute, $values) {
                    foreach ($values as $value) {
                        // For multiselect, check if the value is contained in the stored array/CSV
                        $q->orWhereAttr($attribute->name, 'LIKE', "%{$value}%");
                    }
                });
            });
    }

    /**
     * Build a filter for integer attributes.
     */
    protected function buildIntegerFilter(Attribute $attribute, string $label): Filter
    {
        return Filter::make($attribute->name)
            ->label($label)
            ->form([
                TextInput::make('value')
                    ->label($label)
                    ->numeric()
                    ->placeholder('Enter value'),
            ])
            ->query(function (Builder $query, array $data) use ($attribute) {
                $value = $data['value'] ?? null;
                if ($value === null || $value === '') {
                    return $query;
                }

                return $query->whereAttr($attribute->name, '=', $value);
            })
            ->indicateUsing(function (array $data) use ($label): ?string {
                $value = $data['value'] ?? null;
                if ($value === null || $value === '') {
                    return null;
                }

                return "{$label}: {$value}";
            });
    }

    /**
     * Build a filter for text/html attributes.
     */
    protected function buildTextFilter(Attribute $attribute, string $label): Filter
    {
        return Filter::make($attribute->name)
            ->label($label)
            ->form([
                TextInput::make('value')
                    ->label($label)
                    ->placeholder('Search...'),
            ])
            ->query(function (Builder $query, array $data) use ($attribute) {
                $value = $data['value'] ?? null;
                if ($value === null || $value === '') {
                    return $query;
                }

                return $query->whereAttr($attribute->name, 'LIKE', "%{$value}%");
            })
            ->indicateUsing(function (array $data) use ($label): ?string {
                $value = $data['value'] ?? null;
                if ($value === null || $value === '') {
                    return null;
                }

                return "{$label} contains: {$value}";
            });
    }

    /**
     * Build a filter for belongs_to relationships.
     */
    protected function buildBelongsToFilter(Attribute $attribute, string $label): ?SelectFilter
    {
        if (!$attribute->linked_entity_type_id) {
            return null;
        }

        // Get all entities of the linked type for the dropdown
        $linkedEntities = Entity::where('entity_type_id', $attribute->linked_entity_type_id)
            ->limit(1000) // Reasonable limit for dropdown
            ->get();

        $options = [];
        foreach ($linkedEntities as $entity) {
            // Use entity_id as the display value (could be enhanced to show a name attribute)
            $options[$entity->id] = $entity->entity_id;
        }

        if (empty($options)) {
            return null;
        }

        return SelectFilter::make($attribute->name)
            ->label($label)
            ->options($options)
            ->multiple()
            ->query(function (Builder $query, array $data) use ($attribute) {
                $values = $data['values'] ?? [];
                if (empty($values)) {
                    return $query;
                }

                // Query the entity_attr_links table directly
                return $query->whereExists(function ($q) use ($attribute, $values) {
                    $q->selectRaw('1')
                        ->from('entity_attr_links')
                        ->whereColumn('entity_attr_links.entity_id', '=', 'entities.id')
                        ->where('entity_attr_links.attribute_id', $attribute->id)
                        ->whereIn('entity_attr_links.target_entity_id', $values);
                });
            });
    }

    /**
     * Build a filter for belongs_to_multi relationships.
     */
    protected function buildBelongsToMultiFilter(Attribute $attribute, string $label): ?SelectFilter
    {
        // Same as belongs_to for filtering purposes
        return $this->buildBelongsToFilter($attribute, $label);
    }
}

