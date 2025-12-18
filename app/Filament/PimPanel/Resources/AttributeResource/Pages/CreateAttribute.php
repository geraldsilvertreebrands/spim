<?php

namespace App\Filament\PimPanel\Resources\AttributeResource\Pages;

use App\Filament\PimPanel\Resources\AttributeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;

    /**
     * Mutate the form data before filling the form when creating another record.
     * Preserves all settings except name and display_name.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // When "create another" is used, Filament passes the previous record's data
        // We want to preserve everything except name and display_name
        if (! empty($data)) {
            // Clear name and display_name for the new record
            // but keep all other settings like entity_type_id, data_type, etc.
            $data['name'] = '';
            $data['display_name'] = '';
        }

        return $data;
    }
}
