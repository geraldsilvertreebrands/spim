<?php

namespace App\Filament\Resources\CategoryEntities\Tables;

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

class CategoryEntitiesTable
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
                    ->modalHeading(fn (Entity $record): string => "Category: {$record->id}")
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

        $entityTypeId = 6; // Categories entity type
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
            $columns[] = TextColumn::make("attr_{$attribute->name}")
                ->label($attribute->name)
                ->formatStateUsing(function (Entity $record) use ($attribute, $registry) {
                    try {
                        $ui = $registry->resolve($attribute);
                        return $ui->summarise($record, $attribute);
                    } catch (\Exception $e) {
                        return $record->getAttr($attribute->name) ?? '';
                    }
                })
                ->searchable(false);
        }

        return $columns;
    }

    protected static function getDefaultColumns(): array
    {
        return Attribute::where('entity_type_id', 6)
            ->limit(5)
            ->pluck('name')
            ->toArray();
    }
}
