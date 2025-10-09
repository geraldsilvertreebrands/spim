<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\AbstractEntityTypeResource;
use App\Models\Attribute;
use Filament\Resources\Pages\EditRecord;

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
                ->label('Sync to Magento')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sync to Magento')
                ->modalDescription('This will sync this product to Magento immediately.')
                ->action(function () {
                    /** @var int|null $userId */
                    $userId = auth()->id();

                    \App\Jobs\Sync\SyncSingleProduct::dispatch(
                        $this->record,
                        $userId,
                        'user'
                    );

                    \Filament\Notifications\Notification::make()
                        ->title('Sync queued')
                        ->body('Product will be synced to Magento shortly. Check the Magento Sync page for results.')
                        ->success()
                        ->send();
                }),

            \Filament\Actions\DeleteAction::make(),
        ];
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
            $data[$attribute->name] = $entity->getAttr($attribute->name);
        }

        return $data;
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
                logger()->warning("Failed to save attribute {$key}: " . $e->getMessage());
            }
        }
    }
}

