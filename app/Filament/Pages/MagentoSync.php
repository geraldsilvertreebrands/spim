<?php

namespace App\Filament\Pages;

use App\Jobs\Sync\SyncAllProducts;
use App\Jobs\Sync\SyncAttributeOptions;
use App\Models\EntityType;
use App\Models\SyncRun;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class MagentoSync extends Page implements HasTable
{
    use InteractsWithTable;

    public ?array $verificationResults = null;

    protected string $view = 'filament.pages.magento-sync';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Magento Sync';

    protected static ?string $title = 'Magento Sync';

    protected static ?int $navigationSort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(SyncRun::query()->with(['entityType', 'user'])->latest())
            ->columns([
                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                BadgeColumn::make('sync_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'products',
                        'warning' => 'options',
                        'info' => 'full',
                    ]),

                TextColumn::make('entityType.name')
                    ->label('Entity Type')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'partial',
                        'danger' => ['failed', 'cancelled'],
                        'secondary' => 'running',
                    ]),

                TextColumn::make('total_items')
                    ->label('Total'),

                TextColumn::make('successful_items')
                    ->label('Success')
                    ->color('success'),

                TextColumn::make('failed_items')
                    ->label('Errors')
                    ->color('danger'),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}s" : '-'),

                TextColumn::make('triggered_by')
                    ->label('By')
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === 'user' && $record->user) {
                            return $record->user->name;
                        }
                        return ucfirst($state);
                    }),
            ])
            ->actions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->isRunning())
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Sync')
                    ->modalDescription('Are you sure you want to cancel this running sync? This action cannot be undone.')
                    ->action(function ($record) {
                        try {
                            $record->cancel();

                            Notification::make()
                                ->title('Sync cancelled')
                                ->body('The sync has been successfully cancelled.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Cancel failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('viewErrors')
                    ->label('Errors')
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->failed_items > 0)
                    ->modalHeading('Sync Errors')
                    ->modalContent(function ($record) {
                        $errors = $record->errors()->limit(50)->get();

                        if ($errors->isEmpty()) {
                            return view('filament.components.no-errors');
                        }

                        return view('filament.components.sync-errors', ['errors' => $errors]);
                    }),

                Action::make('viewDetails')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->modalHeading('Sync Details')
                    ->modalWidth('2xl')
                    ->modalContent(function ($record) {
                        return view('filament.components.sync-details', ['syncRun' => $record, 'cache_bust' => time()]);
                    })
                    ->modalSubmitActionLabel(fn ($record) => $record->isRunning() ? 'Cancel Sync' : null)
                    ->modalSubmitAction(fn ($record) => $record->isRunning() ? function () use ($record) {
                        try {
                            $record->cancel();

                            Notification::make()
                                ->title('Sync cancelled')
                                ->body('The sync has been successfully cancelled.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Cancel failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    } : null)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('started_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyAttributes')
                ->label('Verify Attributes')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->modalHeading(fn ($livewire) => empty($livewire->verificationResults) ? 'Verify Attributes' : 'Attribute Verification Results')
                ->modalWidth('5xl')
                ->fillForm(fn () => ['entity_type_id' => null])
                ->form(fn ($livewire) => empty($livewire->verificationResults) ? [
                    Select::make('entity_type_id')
                        ->label('Entity Type')
                        ->options(EntityType::pluck('name', 'id'))
                        ->required()
                        ->helperText('Verify attribute compatibility and sync options with Magento.'),
                ] : [])
                ->modalContent(function ($livewire) {
                    // If we have results, show them instead of the form
                    if (!empty($livewire->verificationResults)) {
                        return view('filament.components.attribute-verification-results', [
                            'results' => $livewire->verificationResults
                        ]);
                    }
                    return null;
                })
                ->modalFooterActions(function ($livewire, $action) {
                    // If we have results, only show Close button
                    if (!empty($livewire->verificationResults)) {
                        return [
                            $action->getModalCancelAction(),
                        ];
                    }
                    // Otherwise show both Run Verification and Cancel
                    return [
                        $action->getModalSubmitAction(),
                        $action->getModalCancelAction(),
                    ];
                })
                ->modalSubmitActionLabel('Run Verification')
                ->modalCancelActionLabel(fn ($livewire) => empty($livewire->verificationResults) ? 'Cancel' : 'Close')
                ->action(function (array $data) {
                    $entityType = EntityType::find($data['entity_type_id']);

                    // Run validation synchronously
                    $validationSync = app(\App\Services\Sync\AttributeValidationSync::class, [
                        'entityType' => $entityType,
                        'syncRun' => null,
                    ]);

                    $result = $validationSync->sync();

                    // Store results in component property
                    $this->verificationResults = [
                        'entity_type' => $entityType->name,
                        'summary' => $result['validation_results']['summary'] ?? 'Validation completed',
                        'type_checks' => $result['validation_results']['type_checks'] ?? [],
                        'option_syncs' => $result['validation_results']['option_syncs'] ?? [],
                        'timestamp' => now()->toDateTimeString(),
                    ];

                    // Don't close the modal - it will refresh to show results
                    $this->halt();
                })
                ->closeModalByClickingAway(false)
                ->after(function () {
                    // Clear results when modal is closed
                    $this->verificationResults = null;
                }),

            Action::make('syncProducts')
                ->label('Sync Products')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    Select::make('entity_type_id')
                        ->label('Entity Type')
                        ->options(EntityType::pluck('name', 'id'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $entityType = EntityType::find($data['entity_type_id']);
                    /** @var int|null $userId */
                    $userId = Auth::id();

                    SyncAllProducts::dispatch(
                        $entityType,
                        $userId,
                        'user'
                    );

                    Notification::make()
                        ->title('Product sync queued')
                        ->body("Syncing all products for {$entityType->name}")
                        ->success()
                        ->send();
                }),

            Action::make('syncBySku')
                ->label('Sync by SKU')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->form([
                    Select::make('entity_type_id')
                        ->label('Entity Type')
                        ->options(EntityType::pluck('name', 'id'))
                        ->required(),

                    Textarea::make('skus')
                        ->label('SKUs (one per line)')
                        ->required()
                        ->rows(5)
                        ->helperText('Enter one SKU per line'),
                ])
                ->action(function (array $data) {
                    $entityType = EntityType::find($data['entity_type_id']);
                    $skus = array_filter(array_map('trim', explode("\n", $data['skus'])));
                    /** @var int|null $userId */
                    $userId = Auth::id();

                    foreach ($skus as $sku) {
                        $entity = \App\Models\Entity::where('entity_type_id', $entityType->id)
                            ->where('entity_id', $sku)
                            ->first();

                        if ($entity) {
                            \App\Jobs\Sync\SyncSingleProduct::dispatch(
                                $entity,
                                $userId,
                                'user'
                            );
                        }
                    }

                    Notification::make()
                        ->title('Product syncs queued')
                        ->body("Syncing " . count($skus) . " product(s)")
                        ->success()
                        ->send();
                }),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    Notification::make()
                        ->title('Refreshed')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\MagentoSyncStats::class,
        ];
    }
}
