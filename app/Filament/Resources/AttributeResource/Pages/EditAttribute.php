<?php

namespace App\Filament\Resources\AttributeResource\Pages;

use App\Filament\Resources\AttributeResource;
use App\Jobs\Sync\SyncAttributeOptions;
use App\Models\SyncRun;
use App\Services\MagentoApiClient;
use App\Services\Sync\AttributeOptionSync;
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
                    try {
                        // Create sync run record for tracking
                        $syncRun = SyncRun::create([
                            'entity_type_id' => $this->record->entity_type_id,
                            'sync_type' => 'options',
                            'started_at' => now(),
                            'status' => 'running',
                            'triggered_by' => 'user',
                            'user_id' => auth()->id(),
                        ]);

                        // Run sync synchronously for this specific attribute
                        $magentoClient = app(MagentoApiClient::class);
                        $sync = app(AttributeOptionSync::class, [
                            'magentoClient' => $magentoClient,
                            'entityType' => $this->record->entityType,
                            'syncRun' => $syncRun,
                        ]);

                        // Sync only this specific attribute
                        $sync->syncSingleAttribute($this->record);

                        // Update sync run with results
                        $syncRun->update([
                            'completed_at' => now(),
                            'status' => 'completed',
                            'total_items' => 1,
                            'successful_items' => 1,
                            'failed_items' => 0,
                            'skipped_items' => 0,
                        ]);

                        Notification::make()
                            ->title('Options synced successfully')
                            ->body("Options for '{$this->record->name}' have been synced from Magento.")
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        // Update sync run with error if it exists
                        if (isset($syncRun)) {
                            $syncRun->update([
                                'completed_at' => now(),
                                'status' => 'failed',
                                'total_items' => 1,
                                'successful_items' => 0,
                                'failed_items' => 1,
                                'skipped_items' => 0,
                            ]);
                        }

                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
