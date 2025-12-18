<?php

namespace App\Filament\Shared\Pages;

use App\Filament\PimPanel\Resources\AbstractEntityTypeResource;
use App\Models\Attribute;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class AbstractEditEntityRecord extends EditRecord
{
    /**
     * Get the entity type from the resource.
     */
    protected function getEntityType()
    {
        /** @var AbstractEntityTypeResource $resource */
        $resource = static::getResource();

        return $resource::getEntityTypeName();
    }

    /**
     * Get the entity type ID.
     */
    protected function getEntityTypeId(): int
    {
        /** @var AbstractEntityTypeResource $resource */
        $resource = static::getResource();

        return $resource::getEntityType()->id;
    }

    /**
     * Get header actions (like delete button).
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('syncToMagento')
                ->label('Sync with Magento')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sync with Magento')
                ->modalDescription('This will sync this product with Magento immediately.')
                ->action(function () {
                    try {
                        /** @var int|null $userId */
                        $userId = Auth::id();

                        // Use SyncRunService to wrap execution
                        $syncRunService = app(\App\Services\Sync\SyncRunService::class);
                        $entity = $this->record;
                        $entityType = $entity->entityType;

                        $syncRunService->run('products', $entityType, $userId, 'user', function (\App\Models\SyncRun $syncRun) use ($entity, $entityType) {
                            $sync = app(\App\Services\Sync\ProductSync::class, [
                                'entityType' => $entityType,
                                'sku' => $entity->entity_id,
                                'syncRun' => $syncRun,
                            ]);

                            $stats = $sync->sync();

                            return [
                                'created' => $stats['created'] ?? 0,
                                'updated' => $stats['updated'] ?? 0,
                                'errors' => $stats['errors'] ?? 0,
                                'skipped' => $stats['skipped'] ?? 0,
                            ];
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Sync completed')
                            ->body('Product was synced to Magento successfully.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        if (isset($syncRun)) {
                            $syncRun->markFailed($e->getMessage());
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            \Filament\Actions\DeleteAction::make(),
        ];
    }

    /**
     * Handle the "Add as Eval" event from pipeline metadata links.
     */
    #[On('addAsEval')]
    public function handleAddAsEval(string $pipelineId, $currentValue): void
    {
        try {
            // Get the pipeline
            $pipeline = \App\Models\Pipeline::findOrFail($pipelineId);

            // Check if eval already exists for this entity
            $existingEval = $pipeline->evals()
                ->where('entity_id', $this->record->id)
                ->first();

            if ($existingEval) {
                \Filament\Notifications\Notification::make()
                    ->title('Eval Already Exists')
                    ->body('An eval for this entity already exists. Update it from the pipeline edit page.')
                    ->warning()
                    ->send();

                return;
            }

            // Create the eval
            $pipeline->evals()->create([
                'entity_id' => $this->record->id,
                'desired_output' => ['value' => $currentValue],
                'notes' => 'Added from entity edit form',
                'input_hash' => '', // Will be calculated on first run
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Eval Created')
                ->body('The current value has been added as an evaluation test case.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error Creating Eval')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Load attribute values into the form before filling.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $entity = $this->record;
        $entityTypeId = $this->getEntityTypeId();

        $attributes = Attribute::where('entity_type_id', $entityTypeId)->get();

        foreach ($attributes as $attribute) {
            // For overridable attributes, only load the actual override value (if set)
            // This keeps the override input empty if no override exists
            if ($attribute->editable === 'overridable') {
                $overrideValue = $this->getOverrideValue($entity, $attribute);
                $data[$attribute->name] = $overrideValue;
            } else {
                // For editable/read-only attributes, load the current value
                $data[$attribute->name] = $entity->getAttr($attribute->name);
            }
        }

        return $data;
    }

    /**
     * Get the override value directly from the database (null if not set).
     */
    protected function getOverrideValue($entity, $attribute)
    {
        $row = \Illuminate\Support\Facades\DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $attribute->id)
            ->first();

        return $row?->value_override;
    }

    /**
     * Strip attribute fields before saving the entity model.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove all attribute fields - they'll be saved in afterSave
        $cleanData = [];
        foreach ($data as $key => $value) {
            // Only keep entity_id (which is read-only anyway)
            if ($key === 'entity_id') {
                $cleanData[$key] = $value;
            }
        }

        return $cleanData;
    }

    /**
     * After saving the entity, save attribute values.
     */
    protected function afterSave(): void
    {
        $entity = $this->record;
        $data = $this->form->getState();

        // Save all attribute values using magic setters
        foreach ($data as $key => $value) {
            // Skip entity_id
            if ($key === 'entity_id') {
                continue;
            }

            // Save attribute
            try {
                $entity->{$key} = $value;
            } catch (\Exception $e) {
                // Log but don't fail if an attribute fails to save
                logger()->warning("Failed to save attribute {$key}: ".$e->getMessage());
            }
        }
    }
}
