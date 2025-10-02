<?php

namespace App\Filament\Resources\AttributeSectionResource\Pages;

use App\Filament\Resources\AttributeSectionResource;
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

