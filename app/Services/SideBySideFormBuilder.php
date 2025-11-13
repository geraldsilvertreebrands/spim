<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Entity;
use Filament\Forms;

/**
 * Service for building side-by-side editing form components.
 *
 * Reuses EntityFormBuilder::buildInputField() to ensure consistency
 * between single-entity and side-by-side editing modes.
 */
class SideBySideFormBuilder
{
    public function __construct(
        protected EntityFormBuilder $entityFormBuilder
    ) {}

    /**
     * Build a form input field for a specific entity and attribute.
     *
     * This method wraps EntityFormBuilder::buildInputField() to ensure
     * both editing modes use identical field generation logic.
     */
    public function buildFieldForEntity(Attribute $attribute, string $entityId): mixed
    {
        // Create a namespaced field name: formData.{entityId}.{attributeName}
        $fieldName = "formData.{$entityId}.{$attribute->name}";

        // Get the base field from EntityFormBuilder (reusing existing logic)
        $field = $this->buildInputFieldForAttribute($attribute, $fieldName);

        // Configure for side-by-side context
        if ($field) {
            $field = $field
                ->hiddenLabel()  // Labels are in the left column
                ->extraAttributes(['class' => 'w-full'])
                ->columnSpanFull();
        }

        return $field;
    }

    /**
     * Build input field based on attribute data type.
     * Mirrors EntityFormBuilder::buildInputField() logic.
     */
    protected function buildInputFieldForAttribute(Attribute $attribute, string $fieldName): mixed
    {
        $dataType = $attribute->data_type;
        $isReadOnly = $attribute->editable === 'no';

        $field = match ($dataType) {
            'integer' => Forms\Components\TextInput::make($fieldName)
                ->numeric()
                ->placeholder('Enter integer value'),

            'text' => Forms\Components\TextInput::make($fieldName)
                ->maxLength(255)
                ->placeholder('Enter text value'),

            'html' => Forms\Components\RichEditor::make($fieldName)
                ->placeholder('Enter HTML content')
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'link',
                    'bulletList',
                    'orderedList',
                ]),

            'json' => Forms\Components\Textarea::make($fieldName)
                ->placeholder('Enter valid JSON')
                ->rows(3),

            'select' => Forms\Components\Select::make($fieldName)
                ->options($attribute->allowedValues())
                ->placeholder('Select an option'),

            'multiselect' => Forms\Components\Select::make($fieldName)
                ->options($attribute->allowedValues())
                ->multiple()
                ->placeholder('Select one or more options'),

            'belongs_to' => Forms\Components\Select::make($fieldName)
                ->options($this->getRelatedEntityOptions($attribute))
                ->searchable()
                ->placeholder('Select related entity'),

            'belongs_to_multi' => Forms\Components\Select::make($fieldName)
                ->options($this->getRelatedEntityOptions($attribute))
                ->multiple()
                ->searchable()
                ->placeholder('Select related entities'),

            default => Forms\Components\TextInput::make($fieldName)
                ->placeholder('Enter value'),
        };

        // Disable field if read-only
        if ($isReadOnly) {
            $field = $field->disabled();
        }

        return $field;
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

    /**
     * Get display metadata for an attribute (for the left column).
     */
    public function getAttributeMetadata(Attribute $attribute): array
    {
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

        return [
            'display_name' => $attribute->display_name ?? ucfirst(str_replace('_', ' ', $attribute->name)),
            'editable_label' => $editableLabel . $syncLabel,
            'data_type' => $attribute->data_type,
            'color_class' => match ($attribute->editable) {
                'yes' => 'text-blue-600',
                'overridable' => 'text-purple-600',
                'no' => 'text-gray-500',
                default => 'text-gray-600',
            },
        ];
    }
}

