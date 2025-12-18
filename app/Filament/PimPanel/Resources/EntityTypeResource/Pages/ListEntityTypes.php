<?php

namespace App\Filament\PimPanel\Resources\EntityTypeResource\Pages;

use App\Filament\PimPanel\Resources\EntityTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEntityTypes extends ListRecords
{
    protected static string $resource = EntityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
