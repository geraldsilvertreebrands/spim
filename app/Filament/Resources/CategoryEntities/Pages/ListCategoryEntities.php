<?php

namespace App\Filament\Resources\CategoryEntities\Pages;

use App\Filament\Resources\CategoryEntities\CategoryEntityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoryEntities extends ListRecords
{
    protected static string $resource = CategoryEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
