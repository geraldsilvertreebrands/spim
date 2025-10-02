<?php

namespace App\Filament\Resources;

use App\Models\Entity;
use App\Models\EntityType;
use App\Services\EntityFormBuilder;
use App\Services\EntityTableBuilder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

abstract class AbstractEntityTypeResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Entities';

    /**
     * Return the entity type name for this resource.
     * Example: 'Product', 'Category', 'Brand'
     */
    abstract public static function getEntityTypeName(): string;

    /**
     * Get the navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return static::getEntityTypeName();
    }

    /**
     * Get the entity type model instance.
     */
    public static function getEntityType(): EntityType
    {
        static $entityType = null;

        if ($entityType === null) {
            $entityType = EntityType::where('name', static::getEntityTypeName())->firstOrFail();
        }

        return $entityType;
    }

    /**
     * Scope queries to this entity type.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('entity_type_id', static::getEntityType()->id);
    }

    /**
     * Define the form schema.
     */
    public static function form(Schema $schema): Schema
    {
        $builder = app(EntityFormBuilder::class);
        $components = $builder->buildComponents(static::getEntityType());

        return $schema->components($components);
    }

    /**
     * Define the table.
     */
    public static function table(Table $table): Table
    {
        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns(static::getEntityType());

        return $table
            ->columns($columns)
            ->filters([
                // Can be overridden in subclass
            ])
            ->actions(static::getTableActions())
            ->bulkActions(static::getBulkActions())
            ->defaultSort('id', 'desc');
    }

    /**
     * Get table row actions.
     * Can be overridden for custom actions.
     */
    protected static function getTableActions(): array
    {
        return [
            \Filament\Actions\Action::make('view')
                ->label('View')
                ->icon('heroicon-o-eye')
                ->modalHeading(fn (Entity $record): string => static::getEntityTypeName() . ": {$record->id}")
                ->modalContent(fn (Entity $record) => view('filament.components.entity-detail-modal', [
                    'entity' => $record,
                    'entityType' => $record->entityType,
                ]))
                ->modalWidth('7xl')
                ->slideOver(),
        ];
    }

    /**
     * Get bulk actions.
     * Can be overridden for custom bulk actions.
     */
    protected static function getBulkActions(): array
    {
        return [
            \Filament\Actions\BulkActionGroup::make([
                \Filament\Actions\DeleteBulkAction::make(),
            ]),
        ];
    }

    // getPages() must be defined by subclasses
}

