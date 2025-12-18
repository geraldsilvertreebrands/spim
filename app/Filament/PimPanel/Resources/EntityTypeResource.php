<?php

namespace App\Filament\PimPanel\Resources;

use App\Filament\PimPanel\Resources\EntityTypeResource\Pages;
use App\Models\EntityType;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class EntityTypeResource extends Resource
{
    protected static ?string $model = EntityType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('display_name')
                ->required()
                ->label('Display Name (Plural)')
                ->helperText('e.g., "Products", "Categories"'),
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('display_name')->searchable()->sortable()->label('Display Name'),
                Tables\Columns\TextColumn::make('description')->limit(60),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntityTypes::route('/'),
            'create' => Pages\CreateEntityType::route('/create'),
            'edit' => Pages\EditEntityType::route('/{record}/edit'),
        ];
    }
}
