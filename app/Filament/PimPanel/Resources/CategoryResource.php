<?php

namespace App\Filament\PimPanel\Resources;

use App\Filament\PimPanel\Resources\CategoryResource\Pages;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class CategoryResource extends AbstractEntityTypeResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    public static function getEntityTypeName(): string
    {
        return 'category';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategories::route('/create'),
            'edit' => Pages\EditCategories::route('/{record}/edit'),
            'side-by-side' => Pages\SideBySideEditCategories::route('/side-by-side'),
        ];
    }
}
