<?php

namespace App\Filament\Resources\ProductEntities\Pages;

use App\Filament\Resources\ProductEntities\ProductEntityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductEntities extends ListRecords
{
    protected static string $resource = ProductEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
