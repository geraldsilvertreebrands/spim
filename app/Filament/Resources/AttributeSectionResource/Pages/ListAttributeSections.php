<?php

namespace App\Filament\Resources\AttributeSectionResource\Pages;

use App\Filament\Resources\AttributeSectionResource;
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

