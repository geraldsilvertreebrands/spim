<?php

namespace App\Filament\PimPanel\Resources\AttributeSectionResource\Pages;

use App\Filament\PimPanel\Resources\AttributeSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttributeSection extends EditRecord
{
    protected static string $resource = AttributeSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
