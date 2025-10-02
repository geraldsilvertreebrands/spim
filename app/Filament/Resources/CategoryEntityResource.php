<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryEntityResource\Pages;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class CategoryEntityResource extends AbstractEntityTypeResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    public static function getEntityTypeName(): string
    {
        return 'Categories';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategories::route('/create'),
            'edit' => Pages\EditCategories::route('/{record}/edit'),
        ];
    }
}

