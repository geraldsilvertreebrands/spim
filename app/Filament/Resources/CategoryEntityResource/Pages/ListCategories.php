<?php

namespace App\Filament\Resources\CategoryEntityResource\Pages;

use App\Filament\Resources\CategoryEntityResource;
use App\Filament\Resources\Pages\AbstractListEntityRecords;

class ListCategories extends AbstractListEntityRecords
{
    protected static string $resource = CategoryEntityResource::class;
}

