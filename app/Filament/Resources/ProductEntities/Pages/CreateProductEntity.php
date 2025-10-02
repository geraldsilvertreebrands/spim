<?php

namespace App\Filament\Resources\ProductEntities\Pages;

use App\Filament\Resources\ProductEntities\ProductEntityResource;
use App\Models\Entity;
use App\Models\EntityType;
use Filament\Resources\Pages\CreateRecord;

class CreateProductEntity extends CreateRecord
{
    protected static string $resource = ProductEntityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get entity type ID
        $entityTypeId = EntityType::where('name', 'Product')->value('id');

        // Set entity_type_id and use entity_id as the ID
        $entityId = $data['entity_id'];

        // Remove attr_ fields - they'll be saved in afterCreate
        $cleanData = [];
        foreach ($data as $key => $value) {
            if (!str_starts_with($key, 'attr_')) {
                $cleanData[$key] = $value;
            }
        }

        unset($cleanData['entity_id']);
        $cleanData['id'] = $entityId;
        $cleanData['entity_id'] = $entityId;
        $cleanData['entity_type_id'] = $entityTypeId;

        return $cleanData;
    }

    protected function afterCreate(): void
    {
        // Save attribute values using magic setters
        $entity = $this->record;
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'attr_')) {
                $attributeName = substr($key, 5); // Remove 'attr_' prefix
                $entity->{$attributeName} = $value;
            }
        }
    }
}
