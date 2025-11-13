<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductEntityResource\Pages;

class ProductEntityResource extends AbstractEntityTypeResource
{
    public static function getEntityTypeName(): string
    {
        return 'Product';
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

