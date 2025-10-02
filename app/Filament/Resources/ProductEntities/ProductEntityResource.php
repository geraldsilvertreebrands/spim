<?php

namespace App\Filament\Resources\ProductEntities;

use App\Filament\Resources\ProductEntities\Pages\CreateProductEntity;
use App\Filament\Resources\ProductEntities\Pages\EditProductEntity;
use App\Filament\Resources\ProductEntities\Pages\ListProductEntities;
use App\Filament\Resources\ProductEntities\Schemas\ProductEntityForm;
use App\Filament\Resources\ProductEntities\Tables\ProductEntitiesTable;
use App\Models\Entity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductEntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Entities';

    protected static ?string $navigationLabel = 'Products';

    protected static function getEntityTypeId(): int
    {
        static $entityTypeId = null;
        if ($entityTypeId === null) {
            $entityTypeId = \App\Models\EntityType::where('name', 'Product')->value('id');
        }
        return $entityTypeId;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('entity_type_id', static::getEntityTypeId());
    }

    public static function form(Schema $schema): Schema
    {
        return ProductEntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductEntitiesTable::configure($table);
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
            'index' => ListProductEntities::route('/'),
            'create' => CreateProductEntity::route('/create'),
            'edit' => EditProductEntity::route('/{record}/edit'),
        ];
    }
}
