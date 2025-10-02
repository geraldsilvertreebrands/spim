<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

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
                ->helperText('Unique identifier for this entity')
                ->columnSpanFull(),
        ];

        // Get all attributes with their sections
        $attributes = Attribute::where('entity_type_id', $entityType->id)
            ->with('attributeSection')
            ->orderBy('sort_order')
            ->get();

        // Group attributes by section
        $grouped = $attributes->groupBy(function ($attr) {
            return $attr->attribute_section_id ?? 'unsectioned';
        });

        // Get sections in order
        $sections = \App\Models\AttributeSection::where('entity_type_id', $entityType->id)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        // Add sections with attributes
        foreach ($sections as $sectionId => $section) {
            if (isset($grouped[$sectionId])) {
                $sectionComponents = [];
                foreach ($grouped[$sectionId] as $attribute) {
                    $component = $this->buildComponent($attribute);
                    if ($component) {
                        $sectionComponents[] = $component;
                    }
                }

                if (!empty($sectionComponents)) {
                    $components[] = Section::make($section->name)
                        ->schema($sectionComponents)
                        ->columns(1)  // Changed to 1 column since fields now have their own two-column layout
                        ->collapsible()
                        ->columnSpanFull();  // Force full width
                }
            }
        }

        // Add unsectioned attributes
        if (isset($grouped['unsectioned'])) {
            $unsectionedComponents = [];
            foreach ($grouped['unsectioned'] as $attribute) {
                $component = $this->buildComponent($attribute);
                if ($component) {
                    $unsectionedComponents[] = $component;
                }
            }

            if (!empty($unsectionedComponents)) {
                $components[] = Section::make('Other Attributes')
                    ->schema($unsectionedComponents)
                    ->columns(1)  // Changed to 1 column since fields now have their own two-column layout
                    ->collapsible()
                    ->columnSpanFull();  // Force full width
            }
        }

        return $components;
    }

    /**
     * Build a single form component for an attribute.
     * Uses Magento-style two-column layout with attribute name/type on left, field on right.
     */
    public function buildComponent(Attribute $attribute)
    {
        $name = $attribute->name;
        $displayName = $attribute->display_name ?? ucfirst(str_replace('_', ' ', $attribute->name));

        // Create the form field without label (since we'll show it in the left column)
        $field = $this->buildFieldComponent($attribute);

        // Wrap in a grid with label on left, field on right
        return Grid::make([
            'default' => 12,
        ])
            ->schema([
                // Left column: Attribute name and metadata (using Placeholder with hiddenLabel)
                Forms\Components\Placeholder::make('_label_' . $name)
                    ->hiddenLabel()
                    ->content(function () use ($displayName, $attribute) {
                        $typeColor = match ($attribute->attribute_type) {
                            'versioned' => 'text-blue-600',
                            'input' => 'text-green-600',
                            'timeseries' => 'text-purple-600',
                            default => 'text-gray-600',
                        };

                        return view('filament.components.attribute-label-wrapper', [
                            'displayName' => $displayName,
                            'attributeType' => $attribute->attribute_type,
                            'dataType' => $attribute->data_type,
                        ]);
                    })
                    ->columnSpan(3),

                // Right column: Form field
                $field->columnSpan(9),
            ])
            ->columnSpanFull()
            ->extraAttributes(['style' => 'margin-bottom: 0;']); // Minimal spacing between rows
    }

    /**
     * Build the actual form field component for an attribute.
     */
    protected function buildFieldComponent(Attribute $attribute)
    {
        $name = $attribute->name;

        return match ($attribute->data_type) {
            'integer' => Forms\Components\TextInput::make($name)
                ->hiddenLabel()
                ->numeric()
                ->placeholder('Enter integer value'),

            'text' => Forms\Components\TextInput::make($name)
                ->hiddenLabel()
                ->maxLength(255)
                ->placeholder('Enter text value'),

            'html' => Forms\Components\RichEditor::make($name)
                ->hiddenLabel()
                ->placeholder('Enter HTML content'),

            'json' => Forms\Components\Textarea::make($name)
                ->hiddenLabel()
                ->placeholder('Enter valid JSON')
                ->rows(5),

            'select' => Forms\Components\Select::make($name)
                ->hiddenLabel()
                ->options($attribute->allowedValues())
                ->placeholder('Select an option'),

            'multiselect' => Forms\Components\Select::make($name)
                ->hiddenLabel()
                ->options($attribute->allowedValues())
                ->multiple()
                ->placeholder('Select one or more options'),

            'belongs_to' => Forms\Components\Select::make($name)
                ->hiddenLabel()
                ->options($this->getRelatedEntityOptions($attribute))
                ->searchable()
                ->placeholder('Select related entity'),

            'belongs_to_multi' => Forms\Components\Select::make($name)
                ->hiddenLabel()
                ->options($this->getRelatedEntityOptions($attribute))
                ->multiple()
                ->searchable()
                ->placeholder('Select related entities'),

            default => Forms\Components\TextInput::make($name)
                ->hiddenLabel()
                ->placeholder('Enter value'),
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

