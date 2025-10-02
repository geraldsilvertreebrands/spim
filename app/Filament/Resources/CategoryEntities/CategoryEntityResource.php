<?php

namespace App\Filament\Resources\CategoryEntities;

use App\Filament\Resources\CategoryEntities\Pages\CreateCategoryEntity;
use App\Filament\Resources\CategoryEntities\Pages\EditCategoryEntity;
use App\Filament\Resources\CategoryEntities\Pages\ListCategoryEntities;
use App\Filament\Resources\CategoryEntities\Schemas\CategoryEntityForm;
use App\Filament\Resources\CategoryEntities\Tables\CategoryEntitiesTable;
use App\Models\Entity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CategoryEntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Entities';

    protected static ?string $navigationLabel = 'Categories';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('entity_type_id', 6);
    }

    public static function form(Schema $schema): Schema
    {
        return CategoryEntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoryEntitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategoryEntities::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
