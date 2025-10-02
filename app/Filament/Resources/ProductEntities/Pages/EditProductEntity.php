<?php

namespace App\Filament\Resources\ProductEntities\Pages;

use App\Filament\Resources\ProductEntities\ProductEntityResource;
use App\Models\Attribute;
use App\Models\EntityType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductEntity extends EditRecord
{
    protected static string $resource = ProductEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load attribute values into the form
        $entity = $this->record;
        $entityTypeId = EntityType::where('name', 'Product')->value('id');

        $attributes = Attribute::where('entity_type_id', $entityTypeId)->get();

        foreach ($attributes as $attribute) {
            $key = "attr_{$attribute->name}";
            $data[$key] = $entity->getAttr($attribute->name);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove attr_ fields - they'll be saved in afterSave
        $cleanData = [];
        foreach ($data as $key => $value) {
            if (!str_starts_with($key, 'attr_')) {
                $cleanData[$key] = $value;
            }
        }

        return $cleanData;
    }

    protected function afterSave(): void
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
