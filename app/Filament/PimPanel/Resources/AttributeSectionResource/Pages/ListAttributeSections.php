<?php

namespace App\Filament\PimPanel\Resources\AttributeSectionResource\Pages;

use App\Filament\PimPanel\Resources\AttributeSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttributeSections extends ListRecords
{
    protected static string $resource = AttributeSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
