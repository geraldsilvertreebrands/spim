<?php

namespace App\Filament\Resources\CategoryEntities\Pages;

use App\Filament\Resources\CategoryEntities\CategoryEntityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoryEntity extends CreateRecord
{
    protected static string $resource = CategoryEntityResource::class;
}
