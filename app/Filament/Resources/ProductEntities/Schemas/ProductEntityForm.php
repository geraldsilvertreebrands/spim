<?php

namespace App\Filament\Resources\ProductEntities\Schemas;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use Filament\Forms;
use Filament\Schemas\Schema;

class ProductEntityForm
{
    public static function configure(Schema $schema): Schema
    {
        $entityTypeId = EntityType::where('name', 'Product')->value('id');

        $components = [
            Forms\Components\TextInput::make('entity_id')
                ->label('ID / SKU')
                ->required()
                ->unique(Entity::class, 'entity_id', ignoreRecord: true)
                ->helperText('Unique identifier for this entity'),
        ];

        // Get all attributes for this entity type
        $attributes = Attribute::where('entity_type_id', $entityTypeId)
            ->orderBy('name')
            ->get();

        foreach ($attributes as $attribute) {
            $component = static::buildFormComponent($attribute);
            if ($component) {
                $components[] = $component;
            }
        }

        return $schema->components($components);
    }

    protected static function buildFormComponent(Attribute $attribute)
    {
        $name = "attr_{$attribute->name}";
        $label = ucfirst(str_replace('_', ' ', $attribute->name));

        return match ($attribute->data_type) {
            'integer' => Forms\Components\TextInput::make($name)
                ->label($label)
                ->numeric()
                ->helperText($attribute->attribute_type),

            'text' => Forms\Components\TextInput::make($name)
                ->label($label)
                ->maxLength(255)
                ->helperText($attribute->attribute_type),

            'html' => Forms\Components\RichEditor::make($name)
                ->label($label)
                ->helperText($attribute->attribute_type),

            'json' => Forms\Components\Textarea::make($name)
                ->label($label)
                ->helperText('Enter valid JSON'),

            'select' => Forms\Components\Select::make($name)
                ->label($label)
                ->options($attribute->allowedValues())
                ->helperText($attribute->attribute_type),

            'multiselect' => Forms\Components\Select::make($name)
                ->label($label)
                ->options($attribute->allowedValues())
                ->multiple()
                ->helperText($attribute->attribute_type),

            'belongs_to' => Forms\Components\Select::make($name)
                ->label($label)
                ->relationship('entityType', 'name')
                ->searchable()
                ->helperText('Select related entity'),

            'belongs_to_multi' => Forms\Components\Select::make($name)
                ->label($label)
                ->relationship('entityType', 'name')
                ->multiple()
                ->searchable()
                ->helperText('Select related entities'),

            default => Forms\Components\TextInput::make($name)
                ->label($label),
        };
    }
}
