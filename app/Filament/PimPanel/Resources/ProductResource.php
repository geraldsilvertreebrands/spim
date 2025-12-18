<?php

namespace App\Filament\PimPanel\Resources;

use App\Filament\PimPanel\Resources\ProductResource\Pages;

class ProductResource extends AbstractEntityTypeResource
{
    public static function getEntityTypeName(): string
    {
        return 'product';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'side-by-side' => Pages\SideBySideEditProducts::route('/side-by-side'),
        ];
    }
}
