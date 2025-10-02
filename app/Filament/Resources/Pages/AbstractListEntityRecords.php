<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class AbstractListEntityRecords extends ListRecords
{
    /**
     * Get header actions (like create button).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

