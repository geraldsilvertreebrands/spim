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
                        // Determine color based on editable + sync status
                        $typeColor = match ($attribute->editable) {
                            'yes' => 'text-blue-600',
                            'overridable' => 'text-purple-600',
                            'no' => 'text-gray-500',
                            default => 'text-gray-600',
                        };

                        $editableLabel = match ($attribute->editable) {
                            'yes' => 'Editable',
                            'overridable' => 'Overridable',
                            'no' => 'Read-only',
                        };

                        $syncLabel = match ($attribute->is_sync) {
                            'from_external' => ' (← Sync)',
                            'to_external' => ' (Sync →)',
                            default => '',
                        };

                        return view('filament.components.attribute-label-wrapper', [
                            'displayName' => $displayName,
                            'attributeType' => $editableLabel . $syncLabel,
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
        $isReadOnly = $attribute->editable === 'no';
        $isOverridable = $attribute->editable === 'overridable';

        // For overridable attributes, show current value + override input
        if ($isOverridable) {
            return $this->buildOverridableField($attribute);
        }

        $field = $this->buildInputField($attribute, $name);

        // Disable field if read-only
        if ($isReadOnly) {
            $field = $field->disabled();
        }

        // For pipeline attributes, wrap with pipeline metadata display
        if ($attribute->pipeline_id) {
            return $this->wrapWithPipelineMetadata($attribute, $field);
        }

        return $field;
    }

    /**
     * Build a field component for overridable attributes.
     * Shows current value (read-only) + separate override input.
     */
    protected function buildOverridableField(Attribute $attribute)
    {
        $name = $attribute->name;

        // Check if override exists to determine initial state
        $hasOverrideCheck = function ($record) use ($name) {
            if (!$record) {
                return false;
            }

            $row = \Illuminate\Support\Facades\DB::table('eav_versioned')
                ->where('entity_id', $record->id)
                ->where('attribute_id', \Illuminate\Support\Facades\DB::table('attributes')
                    ->where('entity_type_id', $record->entity_type_id)
                    ->where('name', $name)
                    ->value('id'))
                ->first();
            return $row?->value_override !== null;
        };

        // Build the input field (without helper text - we'll add it separately)
        $inputField = $this->buildInputField($attribute, $name)
            ->hiddenLabel()
            ->placeholder('Enter override value...')
            ->extraAttributes(['x-show' => 'showOverride', 'x-cloak' => true], true);

        $schemaComponents = [
            // Current value display with override link
            Forms\Components\Placeholder::make('_current_' . $name)
                ->hiddenLabel()
                ->content(function ($record) use ($name, $hasOverrideCheck) {
                    if (!$record) {
                        $currentValue = '(not set)';
                        $isEmpty = true;
                        $hasOverride = false;
                    } else {
                        $currentValue = $record->getAttr($name, 'current', '(not set)');
                        $displayValue = is_array($currentValue) ? json_encode($currentValue) : (string)$currentValue;
                        if (empty($displayValue)) {
                            $displayValue = '(not set)';
                        }
                        $currentValue = $displayValue;
                        $isEmpty = empty($currentValue) || $currentValue === '(not set)';
                        $hasOverride = $hasOverrideCheck($record);
                    }

                    return view('filament.components.attribute-overridable-value', [
                        'value' => $currentValue,
                        'isEmpty' => $isEmpty,
                        'hasOverride' => $hasOverride,
                    ]);
                }),

            // Override input field
            $inputField,

            // Helper text (shown only when input is visible)
            Forms\Components\Placeholder::make('_helper_' . $name)
                ->hiddenLabel()
                ->content(fn () => new \Illuminate\Support\HtmlString('<p class="text-xs text-gray-500">Leave empty to use the current value shown above</p>'))
                ->extraAttributes(['x-show' => 'showOverride', 'x-cloak' => true]),
        ];

        // Add pipeline metadata if this is a pipeline attribute
        if ($attribute->pipeline_id) {
            $schemaComponents[] = $this->buildPipelineMetadataComponent($attribute);
        }

        return Grid::make(1)
            ->extraAttributes(function ($record) use ($hasOverrideCheck) {
                $hasOverride = $hasOverrideCheck($record);
                return [
                    'x-data' => '{ showOverride: ' . ($hasOverride ? 'true' : 'false') . ' }',
                ];
            })
            ->schema($schemaComponents)
            ->columnSpanFull();
    }

    /**
     * Wrap a field component with pipeline metadata display.
     */
    protected function wrapWithPipelineMetadata(Attribute $attribute, $field)
    {
        return Grid::make(1)
            ->schema([
                $field,
                $this->buildPipelineMetadataComponent($attribute),
            ])
            ->columnSpanFull();
    }

    /**
     * Build the pipeline metadata component (justification, confidence, add as eval link).
     */
    protected function buildPipelineMetadataComponent(Attribute $attribute)
    {
        return Forms\Components\Placeholder::make('_pipeline_meta_' . $attribute->name)
            ->hiddenLabel()
            ->content(function ($record) use ($attribute) {
                if (!$record) {
                    return '';
                }

                // Get the eav_versioned row to retrieve justification and confidence
                $eavRow = \Illuminate\Support\Facades\DB::table('eav_versioned')
                    ->where('entity_id', $record->id)
                    ->where('attribute_id', $attribute->id)
                    ->first();

                if (!$eavRow) {
                    return '';
                }

                $justification = $eavRow->justification;
                $confidence = $eavRow->confidence;
                $currentValue = $eavRow->value_current;

                // Only show if there's justification or confidence
                if (!$justification && !$confidence) {
                    return '';
                }

                return view('filament.components.pipeline-metadata', [
                    'justification' => $justification,
                    'confidence' => $confidence,
                    'entityId' => $record->id,
                    'pipelineId' => $attribute->pipeline_id,
                    'currentValue' => $currentValue,
                ]);
            });
    }

    /**
     * Build the actual input field based on data type.
     */
    protected function buildInputField(Attribute $attribute, string $name)
    {
        $hiddenLabel = $attribute->editable !== 'overridable';

        return match ($attribute->data_type) {
            'integer' => Forms\Components\TextInput::make($name)
                ->hiddenLabel($hiddenLabel)
                ->numeric()
                ->placeholder('Enter integer value'),

            'text' => Forms\Components\TextInput::make($name)
                ->hiddenLabel($hiddenLabel)
                ->maxLength(255)
                ->placeholder('Enter text value'),

            'html' => Forms\Components\RichEditor::make($name)
                ->hiddenLabel($hiddenLabel)
                ->placeholder('Enter HTML content'),

            'json' => Forms\Components\Textarea::make($name)
                ->hiddenLabel($hiddenLabel)
                ->placeholder('Enter valid JSON')
                ->rows(5),

            'select' => Forms\Components\Select::make($name)
                ->hiddenLabel($hiddenLabel)
                ->options($attribute->allowedValues())
                ->placeholder('Select an option'),

            'multiselect' => Forms\Components\Select::make($name)
                ->hiddenLabel($hiddenLabel)
                ->options($attribute->allowedValues())
                ->multiple()
                ->placeholder('Select one or more options'),

            'belongs_to' => Forms\Components\Select::make($name)
                ->hiddenLabel($hiddenLabel)
                ->options($this->getRelatedEntityOptions($attribute))
                ->searchable()
                ->placeholder('Select related entity'),

            'belongs_to_multi' => Forms\Components\Select::make($name)
                ->hiddenLabel($hiddenLabel)
                ->options($this->getRelatedEntityOptions($attribute))
                ->multiple()
                ->searchable()
                ->placeholder('Select related entities'),

            default => Forms\Components\TextInput::make($name)
                ->hiddenLabel($hiddenLabel)
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

