<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttributeResource\Pages;
use App\Models\Attribute;
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

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('entity_type_id')
                ->label('Entity Type')
                ->options(EntityType::pluck('name', 'id'))
                ->required()
                ->reactive(),
            Forms\Components\TextInput::make('name')
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Internal attribute code (e.g., product_name)'),
            Forms\Components\TextInput::make('display_name')
                ->label('Display Name')
                ->nullable()
                ->helperText('Human-readable name shown in forms (e.g., Product Name). Leave empty to auto-generate from name.'),
            Forms\Components\Select::make('attribute_section_id')
                ->label('Attribute Section')
                ->options(function (callable $get) {
                    $entityTypeId = $get('entity_type_id');
                    if (!$entityTypeId) {
                        return [];
                    }
                    return \App\Models\AttributeSection::where('entity_type_id', $entityTypeId)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->nullable()
                ->helperText('Optional: Group this attribute under a section'),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->required()
                ->helperText('Controls the display order within the section. Lower numbers appear first.'),
            Forms\Components\Select::make('data_type')
                ->options([
                    'integer' => 'Integer',
                    'text' => 'Text',
                    'html' => 'HTML',
                    'json' => 'JSON',
                    'select' => 'Select',
                    'multiselect' => 'Multiselect',
                    'belongs_to' => 'Belongs to',
                    'belongs_to_multi' => 'Belongs to (multi)',
                ])
                ->required()
                ->reactive(),
            Forms\Components\Select::make('attribute_type')
                ->options([
                    'versioned' => 'Versioned',
                    'input' => 'Input',
                    'timeseries' => 'Timeseries',
                ])
                ->required(),
            Forms\Components\Select::make('review_required')
                ->options([
                    'always' => 'Always',
                    'low_confidence' => 'Low confidence (<0.8)',
                    'no' => 'No',
                ])
                ->default('no')
                ->required(),
            Forms\Components\KeyValue::make('allowed_values')
                ->visible(fn (callable $get) => in_array($get('data_type'), ['select','multiselect'], true))
                ->helperText('Dictionary of KEY => Label'),
            Forms\Components\Select::make('linked_entity_type_id')
                ->label('Linked Entity Type')
                ->options(EntityType::pluck('name', 'id'))
                ->visible(fn (callable $get) => in_array($get('data_type'), ['belongs_to','belongs_to_multi'], true)),
            Forms\Components\Toggle::make('is_synced'),
            Forms\Components\TextInput::make('ui_class')->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entityType.name')->sortable()->label('Entity Type'),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('data_type'),
                Tables\Columns\BadgeColumn::make('attribute_type'),
                Tables\Columns\BadgeColumn::make('review_required'),
                Tables\Columns\IconColumn::make('is_synced')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_type_id')
                    ->options(EntityType::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('data_type')
                    ->options([
                        'integer' => 'Integer',
                        'text' => 'Text',
                        'html' => 'HTML',
                        'json' => 'JSON',
                        'select' => 'Select',
                        'multiselect' => 'Multiselect',
                        'belongs_to' => 'Belongs to',
                        'belongs_to_multi' => 'Belongs to (multi)',
                    ]),
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
            'index' => Pages\ListAttributes::route('/'),
            'create' => Pages\CreateAttribute::route('/create'),
            'edit' => Pages\EditAttribute::route('/{record}/edit'),
        ];
    }
}
