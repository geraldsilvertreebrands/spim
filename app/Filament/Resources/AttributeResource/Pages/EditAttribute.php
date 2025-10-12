<?php

namespace App\Filament\Resources\AttributeResource\Pages;

use App\Filament\Resources\AttributeResource;
use App\Jobs\Sync\SyncAttributeOptions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testMapping')
                ->label('Test Magento Mapping')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $this->record->is_sync !== 'no')
                ->action(function () {
                    try {
                        $magentoClient = app(\App\Services\MagentoApiClient::class);

                        // Try to fetch attribute options from Magento
                        $options = $magentoClient->getAttributeOptions($this->record->name);

                        Notification::make()
                            ->title('Magento Mapping Test Successful')
                            ->body("Found " . count($options) . " options in Magento for attribute '{$this->record->name}'")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Magento Mapping Test Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('syncOptions')
                ->label('Sync Options from Magento')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('primary')
                ->visible(fn () =>
                    $this->record->is_sync !== 'no' &&
                    in_array($this->record->data_type, ['select', 'multiselect'])
                )
                ->requiresConfirmation()
                ->modalHeading('Sync Options from Magento')
                ->modalDescription('This will replace SPIM options with options from Magento. Magento is the source of truth.')
                ->action(function () {
                    // Dispatch job to sync options for this specific attribute's entity type
                    /** @var int|null $userId */
                    $userId = auth()->id();

                    SyncAttributeOptions::dispatch(
                        $this->record->entityType,
                        $userId,
                        'user'
                    );

                    Notification::make()
                        ->title('Option sync queued')
                        ->body('Options will be synced from Magento shortly. Check the Magento Sync page for results.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
