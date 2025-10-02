<?php

namespace App\Filament\Resources\ProductEntityResource\Pages;

use App\Filament\Resources\ProductEntityResource;
use App\Filament\Resources\Pages\AbstractCreateEntityRecord;

class CreateProduct extends AbstractCreateEntityRecord
{
    protected static string $resource = ProductEntityResource::class;
}

