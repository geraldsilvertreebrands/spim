<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use Filament\Forms;

class EntityFormBuilder
{
    /**
     * Build form components for an entity type.
     */
    public function buildComponents(EntityType $entityType): array
    {
        $components = [
            Forms\Components\TextInput::make('entity_id')
                ->label('ID / SKU')
                ->required()
                ->unique(Entity::class, 'entity_id', ignoreRecord: true)
                ->helperText('Unique identifier for this entity'),
        ];

        $attributes = Attribute::where('entity_type_id', $entityType->id)
            ->orderBy('name')
            ->get();

        foreach ($attributes as $attribute) {
            $component = $this->buildComponent($attribute);
            if ($component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Build a single form component for an attribute.
     */
    public function buildComponent(Attribute $attribute)
    {
        $name = $attribute->name;
        $label = ucfirst(str_replace('_', ' ', $attribute->name));
        $helperText = $attribute->attribute_type;

        return match ($attribute->data_type) {
            'integer' => Forms\Components\TextInput::make($name)
                ->label($label)
                ->numeric()
                ->helperText($helperText),

            'text' => Forms\Components\TextInput::make($name)
                ->label($label)
                ->maxLength(255)
                ->helperText($helperText),

            'html' => Forms\Components\RichEditor::make($name)
                ->label($label)
                ->helperText($helperText),

            'json' => Forms\Components\Textarea::make($name)
                ->label($label)
                ->helperText('Enter valid JSON')
                ->rows(5),

            'select' => Forms\Components\Select::make($name)
                ->label($label)
                ->options($attribute->allowedValues())
                ->helperText($helperText),

            'multiselect' => Forms\Components\Select::make($name)
                ->label($label)
                ->options($attribute->allowedValues())
                ->multiple()
                ->helperText($helperText),

            'belongs_to' => Forms\Components\Select::make($name)
                ->label($label)
                ->options($this->getRelatedEntityOptions($attribute))
                ->searchable()
                ->helperText('Select related entity'),

            'belongs_to_multi' => Forms\Components\Select::make($name)
                ->label($label)
                ->options($this->getRelatedEntityOptions($attribute))
                ->multiple()
                ->searchable()
                ->helperText('Select related entities'),

            default => Forms\Components\TextInput::make($name)
                ->label($label)
                ->helperText($helperText),
        };
    }

    /**
     * Get options for belongs_to relationship fields.
     */
    protected function getRelatedEntityOptions(Attribute $attribute): array
    {
        if (!$attribute->linked_entity_type_id) {
            return [];
        }

        return Entity::where('entity_type_id', $attribute->linked_entity_type_id)
            ->pluck('entity_id', 'id')
            ->toArray();
    }
}

