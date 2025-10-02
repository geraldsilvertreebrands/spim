<?php

namespace App\Filament\Resources\ProductEntities\Tables;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\UserPreference;
use App\Support\AttributeUiRegistry;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProductEntitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::getColumns())
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Entity $record): string => "Product: {$record->id}")
                    ->modalContent(fn (Entity $record) => view('filament.components.entity-detail-modal', [
                        'entity' => $record,
                        'entityType' => $record->entityType,
                    ]))
                    ->modalWidth('7xl')
                    ->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    protected static function getColumns(): array
    {
        $columns = [
            TextColumn::make('id')
                ->label('ID')
                ->searchable()
                ->sortable(),
        ];

        $entityTypeId = \App\Models\EntityType::where('name', 'Product')->value('id');
        $preferenceKey = "entity_type_{$entityTypeId}_columns";
        $userId = Auth::id();

        $selectedAttributes = $userId
            ? UserPreference::get($userId, $preferenceKey, static::getDefaultColumns())
            : static::getDefaultColumns();

        if (empty($selectedAttributes)) {
            $selectedAttributes = static::getDefaultColumns();
        }

        $attributes = Attribute::where('entity_type_id', $entityTypeId)
            ->whereIn('name', $selectedAttributes)
            ->get();

        $registry = app(AttributeUiRegistry::class);

        foreach ($attributes as $attribute) {
            $columns[] = TextColumn::make($attribute->name)
                ->label($attribute->name)
                ->getStateUsing(function (Entity $record) use ($attribute) {
                    return $record->getAttr($attribute->name);
                })
                ->formatStateUsing(function ($state, Entity $record) use ($attribute, $registry) {
                    try {
                        $ui = $registry->resolve($attribute);
                        return $ui->summarise($record, $attribute);
                    } catch (\Exception $e) {
                        return $state ?? '';
                    }
                })
                ->searchable(false)
                ->sortable(false);
        }

        return $columns;
    }

    protected static function getDefaultColumns(): array
    {
        $entityTypeId = \App\Models\EntityType::where('name', 'Product')->value('id');
        return Attribute::where('entity_type_id', $entityTypeId)
            ->limit(5)
            ->pluck('name')
            ->toArray();
    }
}
