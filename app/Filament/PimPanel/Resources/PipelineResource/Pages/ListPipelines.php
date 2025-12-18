<?php

namespace App\Filament\PimPanel\Resources\PipelineResource\Pages;

use App\Filament\PimPanel\Resources\PipelineResource;
use Filament\Resources\Pages\ListRecords;

class ListPipelines extends ListRecords
{
    protected static string $resource = PipelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
