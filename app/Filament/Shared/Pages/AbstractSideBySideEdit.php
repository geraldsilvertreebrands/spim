<?php

namespace App\Filament\Shared\Pages;

use App\Filament\PimPanel\Resources\AbstractEntityTypeResource;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\UserPreference;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

abstract class AbstractSideBySideEdit extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.side-by-side-edit';

    protected array $entityIdsArray = [];

    public array $entities = [];

    public array $selectedAttributes = [];

    public array $formData = [];

    public array $errors = [];

    /**
     * Mount the page and load entities.
     */
    public function mount(?string $entityIds = null): void
    {
        // Parse entity IDs from parameter
        $entitiesParam = $entityIds ?? request()->query('entityIds', '');
        $this->entityIdsArray = array_filter(explode(',', $entitiesParam));

        if (empty($this->entityIdsArray)) {
            Notification::make()
                ->title('No entities selected')
                ->warning()
                ->send();

            // Set empty state - blade will handle display
            $this->entities = [];
            $this->selectedAttributes = [];

            return;
        }

        // Load entities
        $this->loadEntities();

        // Load selected attributes from preferences
        $this->selectedAttributes = $this->getSelectedAttributes();

        // Initialize form data
        $this->initializeFormData();
    }

    /**
     * Load entity records.
     */
    protected function loadEntities(): void
    {
        /** @var AbstractEntityTypeResource $resource */
        $resource = static::getResource();
        $entityTypeId = $resource::getEntityType()->id;

        $this->entities = Entity::whereIn('id', $this->entityIdsArray)
            ->where('entity_type_id', $entityTypeId)
            ->get()
            ->keyBy('id')
            ->toArray();

        if (empty($this->entities)) {
            Notification::make()
                ->title('No valid entities found')
                ->danger()
                ->send();

            // Set empty state
            $this->entities = [];
            $this->selectedAttributes = [];
        }
    }

    /**
     * Initialize form data from loaded entities.
     * Preserves existing values for unchanged attributes.
     */
    protected function initializeFormData(): void
    {
        foreach ($this->entities as $entityId => $entityData) {
            $entity = Entity::find($entityId);
            if (! $entity) {
                continue;
            }

            // Initialize entity data if not exists
            if (! isset($this->formData[$entityId])) {
                $this->formData[$entityId] = [];
            }

            // Load data for selected attributes
            foreach ($this->selectedAttributes as $attributeName) {
                // Skip if we already have data for this attribute
                if (array_key_exists($attributeName, $this->formData[$entityId] ?? [])) {
                    continue;
                }

                $attribute = $this->getAttribute($attributeName);
                if (! $attribute) {
                    continue;
                }

                // Load value based on attribute editability
                if ($attribute->editable === 'overridable') {
                    $this->formData[$entityId][$attributeName] = $this->getOverrideValue($entity, $attribute);
                } else {
                    $this->formData[$entityId][$attributeName] = $entity->getAttr($attributeName);
                }
            }

            // Remove data for attributes that are no longer selected
            if (isset($this->formData[$entityId])) {
                $this->formData[$entityId] = array_intersect_key(
                    $this->formData[$entityId],
                    array_flip($this->selectedAttributes)
                );
            }
        }
    }

    /**
     * Get override value for an attribute.
     */
    protected function getOverrideValue($entity, $attribute)
    {
        $row = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $attribute->id)
            ->first();

        return $row?->value_override;
    }

    /**
     * Get selected attributes from user preferences or defaults.
     */
    protected function getSelectedAttributes(): array
    {
        /** @var AbstractEntityTypeResource $resource */
        $resource = static::getResource();
        $entityType = $resource::getEntityType();

        $preferenceKey = "entity_type_{$entityType->id}_sidebyside_attributes";
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        $prefs = UserPreference::get($userId, $preferenceKey);
        if ($prefs !== null && is_array($prefs) && ! empty($prefs)) {
            return $prefs;
        }

        // Default: all editable attributes (excluding read-only)
        return Attribute::where('entity_type_id', $entityType->id)
            ->whereIn('editable', ['yes', 'overridable'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get an attribute by name.
     */
    protected function getAttribute(string $name): ?Attribute
    {
        static $attributes = [];

        if (! isset($attributes[$name])) {
            /** @var AbstractEntityTypeResource $resource */
            $resource = static::getResource();
            $entityTypeId = $resource::getEntityType()->id;

            $attributes[$name] = Attribute::where('entity_type_id', $entityTypeId)
                ->where('name', $name)
                ->first();
        }

        return $attributes[$name];
    }

    /**
     * Get all attributes for the entity type.
     */
    protected function getAllAttributes(): array
    {
        /** @var AbstractEntityTypeResource $resource */
        $resource = static::getResource();
        $entityType = $resource::getEntityType();

        return Attribute::where('entity_type_id', $entityType->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure_attributes')
                ->label('Configure Attributes')
                ->icon('heroicon-o-adjustments-horizontal')
                ->modalHeading('Configure Visible Attributes')
                ->modalDescription('Select which attributes to display in the side-by-side editor')
                ->modalSubmitActionLabel('Save')
                ->form([
                    CheckboxList::make('selected_attributes')
                        ->label('Visible Attributes')
                        ->options(function () {
                            $attributes = $this->getAllAttributes();
                            $options = [];
                            foreach ($attributes as $attr) {
                                $attrObj = (object) $attr;
                                $options[$attrObj->name] = $attrObj->display_name
                                    ?? ucfirst(str_replace('_', ' ', $attrObj->name));
                            }

                            return $options;
                        })
                        ->default(fn () => $this->selectedAttributes)
                        ->columns(2)
                        ->gridDirection('row')
                        ->searchable()
                        ->bulkToggleable()
                        ->required()
                        ->minItems(1),
                ])
                ->action(function (array $data) {
                    /** @var AbstractEntityTypeResource $resource */
                    $resource = static::getResource();
                    $entityType = $resource::getEntityType();
                    $preferenceKey = "entity_type_{$entityType->id}_sidebyside_attributes";
                    $userId = Auth::id();

                    if (! $userId) {
                        return;
                    }

                    // Save preferences
                    UserPreference::set($userId, $preferenceKey, $data['selected_attributes'] ?? []);

                    // Update the selected attributes
                    $this->selectedAttributes = $data['selected_attributes'] ?? [];

                    // Reinitialize form data (preserving existing values)
                    $this->initializeFormData();

                    Notification::make()
                        ->title('Attribute preferences saved')
                        ->body(count($this->selectedAttributes).' '.\Illuminate\Support\Str::plural('attribute', count($this->selectedAttributes)).' selected')
                        ->success()
                        ->send();
                }),

            Action::make('save')
                ->label('Save All')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action(fn () => $this->save()),

            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }

    /**
     * Save all entities.
     */
    public function save(): void
    {
        $this->errors = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($this->formData as $entityId => $attributes) {
            $entity = Entity::find($entityId);
            if (! $entity) {
                $errorCount++;
                $this->errors[$entityId] = 'Entity not found';

                continue;
            }

            try {
                foreach ($attributes as $attributeName => $value) {
                    // Skip entity_id
                    if ($attributeName === 'entity_id') {
                        continue;
                    }

                    try {
                        // Use the Entity magic setter (same as single-entity edit)
                        $entity->{$attributeName} = $value;
                    } catch (\Exception $e) {
                        // Log but continue with other attributes
                        logger()->warning("Failed to save attribute {$attributeName} for entity {$entityId}: ".$e->getMessage());
                        if (! isset($this->errors[$entityId])) {
                            $this->errors[$entityId] = [];
                        }
                        $this->errors[$entityId][$attributeName] = $e->getMessage();
                        $errorCount++;
                    }
                }
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->errors[$entityId] = $e->getMessage();
            }
        }

        // Show notifications
        if ($successCount > 0) {
            Notification::make()
                ->title("Successfully saved {$successCount} ".\Illuminate\Support\Str::plural('entity', $successCount))
                ->success()
                ->send();
        }

        if ($errorCount > 0) {
            Notification::make()
                ->title("{$errorCount} ".\Illuminate\Support\Str::plural('error', $errorCount).' occurred')
                ->body('Some attributes could not be saved. Check the form for details.')
                ->danger()
                ->send();
        }

        // Reload data
        $this->initializeFormData();
    }

    /**
     * Get the page title.
     */
    public function getTitle(): string
    {
        return 'Edit '.count($this->entities).' Entities Side-by-Side';
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string
    {
        return $this->getTitle();
    }

    /**
     * Get the current page URL.
     */
    public function getCurrentUrl(): string
    {
        return static::getResource()::getUrl('side-by-side', [
            'entityIds' => implode(',', $this->entityIdsArray),
        ]);
    }
}
