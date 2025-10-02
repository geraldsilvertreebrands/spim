<?php

namespace App\Filament\Resources\CategoryEntities\Pages;

use App\Filament\Resources\CategoryEntities\CategoryEntityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoryEntity extends EditRecord
{
    protected static string $resource = CategoryEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
