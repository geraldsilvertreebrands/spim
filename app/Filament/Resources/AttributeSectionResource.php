<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttributeSectionResource\Pages;
use App\Models\AttributeSection;
use App\Models\EntityType;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use UnitEnum;
use BackedEnum;

class AttributeSectionResource extends Resource
{
    protected static ?string $model = AttributeSection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('entity_type_id')
                ->label('Entity Type')
                ->options(EntityType::pluck('name', 'id'))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->required()
                ->helperText('Controls the display order of sections. Lower numbers appear first.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entityType.name')
                    ->sortable()
                    ->searchable()
                    ->label('Entity Type'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->label('Sort Order'),
                Tables\Columns\TextColumn::make('attributes_count')
                    ->counts('attributes')
                    ->label('# Attributes'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('entity_type_id')
                    ->options(EntityType::pluck('name', 'id'))
                    ->label('Entity Type'),
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
            'index' => Pages\ListAttributeSections::route('/'),
            'create' => Pages\CreateAttributeSection::route('/create'),
            'edit' => Pages\EditAttributeSection::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Attribute Sections';
    }
}

