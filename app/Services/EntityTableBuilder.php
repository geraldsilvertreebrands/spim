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
            ->searchable(false)
            ->sortable(false)
            ->limit(50)
            ->wrap();
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

