<?php

namespace App\Filament\PimPanel\Resources\CategoryResource\Pages;

use App\Filament\PimPanel\Resources\CategoryResource;
use App\Filament\Shared\Pages\AbstractListEntityRecords;

class ListCategories extends AbstractListEntityRecords
{
    protected static string $resource = CategoryResource::class;
}
