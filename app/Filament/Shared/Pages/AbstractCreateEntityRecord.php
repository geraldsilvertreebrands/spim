<?php

namespace App\Filament\Shared\Pages;

use App\Filament\PimPanel\Resources\AbstractEntityTypeResource;
use Filament\Resources\Pages\CreateRecord;

class AbstractCreateEntityRecord extends CreateRecord
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
     * Mutate form data before creating the entity.
     * Strips out attribute fields and prepares entity data.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $entityId = $data['entity_id'];

        // Remove attribute fields - they'll be saved in afterCreate
        $cleanData = [];
        foreach ($data as $key => $value) {
            // Only keep entity_id, skip all attribute fields
            if ($key === 'entity_id') {
                $cleanData[$key] = $value;
            }
        }

        // Set up the entity record
        $cleanData['id'] = $entityId;
        $cleanData['entity_id'] = $entityId;
        $cleanData['entity_type_id'] = $this->getEntityTypeId();

        return $cleanData;
    }

    /**
     * After creating the entity, save attribute values.
     */
    protected function afterCreate(): void
    {
        $entity = $this->record;
        $data = $this->form->getState();

        // Save all attribute values using magic setters
        foreach ($data as $key => $value) {
            // Skip entity_id as it's already saved
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
