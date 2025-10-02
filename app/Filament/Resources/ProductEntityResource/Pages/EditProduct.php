<?php

namespace App\Filament\Resources\ProductEntityResource\Pages;

use App\Filament\Resources\ProductEntityResource;
use App\Filament\Resources\Pages\AbstractEditEntityRecord;

class EditProduct extends AbstractEditEntityRecord
{
    protected static string $resource = ProductEntityResource::class;
}

