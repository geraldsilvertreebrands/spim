<?php

namespace App\Filament\Resources\ProductEntityResource\Pages;

use App\Filament\Resources\ProductEntityResource;
use App\Filament\Resources\Pages\AbstractListEntityRecords;

class ListProducts extends AbstractListEntityRecords
{
    protected static string $resource = ProductEntityResource::class;
}

