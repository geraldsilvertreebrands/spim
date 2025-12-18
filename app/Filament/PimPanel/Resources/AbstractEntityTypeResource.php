<?php

namespace App\Filament\PimPanel\Resources;

use App\Models\Entity;
use App\Models\EntityType;
use App\Services\EntityFilterBuilder;
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
        return static::getPluralLabel();
    }

    /**
     * Get the singular model label.
     */
    public static function getModelLabel(): string
    {
        return static::getEntityTypeName();
    }

    /**
     * Get the plural model label (from entity_types.display_name).
     */
    public static function getPluralLabel(): ?string
    {
        try {
            return static::getEntityType()->display_name;
        } catch (\Exception $e) {
            // Fallback if entity type doesn't exist yet
            return \Illuminate\Support\Str::plural(static::getEntityTypeName());
        }
    }

    /**
     * Get the entity type model instance.
     */
    public static function getEntityType(): EntityType
    {
        // Use a per-class cache to avoid conflicts between different resources
        static $entityTypes = [];

        $className = static::class;

        if (! isset($entityTypes[$className])) {
            $entityTypes[$className] = EntityType::where('name', static::getEntityTypeName())->firstOrFail();
        }

        return $entityTypes[$className];
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
        $tableBuilder = app(EntityTableBuilder::class);
        $filterBuilder = app(EntityFilterBuilder::class);

        $columns = $tableBuilder->buildColumns(static::getEntityType());
        $filters = $filterBuilder->buildFilters(static::getEntityType());

        return $table
            ->columns($columns)
            ->filters($filters)
            ->actions(static::getTableActions())
            ->bulkActions(static::getBulkActions())
            ->defaultSort('id', 'desc')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession();
    }

    /**
     * Get table row actions.
     * Can be overridden for custom actions.
     */
    protected static function getTableActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
        ];
    }

    /**
     * Get bulk actions.
     * Can be overridden for custom bulk actions.
     */
    protected static function getBulkActions(): array
    {
        return [
            \Filament\Actions\BulkAction::make('edit_side_by_side')
                ->label('Edit side-by-side')
                ->icon('heroicon-o-table-cells')
                ->action(function ($records) {
                    $entityIds = $records->pluck('id')->toArray();

                    // Validate entity count (2-15)
                    if (count($entityIds) < 2) {
                        \Filament\Notifications\Notification::make()
                            ->title('Please select at least 2 entities')
                            ->warning()
                            ->send();

                        return;
                    }

                    if (count($entityIds) > 15) {
                        \Filament\Notifications\Notification::make()
                            ->title('Please select no more than 15 entities')
                            ->body('Side-by-side editing is limited to 15 entities at a time.')
                            ->warning()
                            ->send();

                        return;
                    }

                    // Redirect to side-by-side page with entity IDs
                    $url = static::getUrl('side-by-side', [
                        'entityIds' => implode(',', $entityIds),
                    ]);

                    return redirect($url);
                }),
            \Filament\Actions\DeleteBulkAction::make(),
        ];
    }

    // getPages() must be defined by subclasses
}
