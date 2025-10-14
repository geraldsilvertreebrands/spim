<?php

namespace App\Filament\Resources\Pages;

use App\Models\Attribute;
use App\Models\UserPreference;
use App\Services\EntityTableBuilder;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class AbstractListEntityRecords extends ListRecords
{
    /**
     * Get header actions (like create button).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('configure_columns')
                ->label('Configure Columns')
                ->icon('heroicon-o-adjustments-horizontal')
                ->modalHeading('Configure Table Columns')
                ->modalDescription('Select which attributes to display in the table')
                ->modalSubmitActionLabel('Save')
                ->form([
                    CheckboxList::make('selected_attributes')
                        ->label('Visible Columns')
                        ->options(function () {
                            $entityType = $this->getResource()::getEntityType();
                            $attributes = Attribute::where('entity_type_id', $entityType->id)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->get();

                            $options = [];
                            foreach ($attributes as $attr) {
                                $options[$attr->name] = $attr->display_name
                                    ?? ucfirst(str_replace('_', ' ', $attr->name));
                            }

                            return $options;
                        })
                        ->default(function () {
                            $entityType = $this->getResource()::getEntityType();
                            $preferenceKey = "entity_type_{$entityType->id}_columns";
                            $userId = auth()->id();

                            if ($userId) {
                                $prefs = UserPreference::get($userId, $preferenceKey);
                                if ($prefs) {
                                    return $prefs;
                                }
                            }

                            // Return default attributes (first 5)
                            return Attribute::where('entity_type_id', $entityType->id)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->limit(5)
                                ->pluck('name')
                                ->toArray();
                        })
                        ->columns(2)
                        ->gridDirection('row')
                        ->searchable()
                        ->bulkToggleable(),
                ])
                ->action(function (array $data) {
                    $entityType = $this->getResource()::getEntityType();
                    $preferenceKey = "entity_type_{$entityType->id}_columns";
                    $userId = auth()->id();

                    if ($userId) {
                        UserPreference::set($userId, $preferenceKey, $data['selected_attributes'] ?? []);

                        Notification::make()
                            ->title('Column preferences saved')
                            ->success()
                            ->send();

                        // Redirect to refresh the page with new columns
                        return redirect()->to($this->getUrl());
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}

