<?php

namespace App\Filament\Pages;

use App\Services\EavWriter;
use App\Services\ReviewQueueService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReviewQueue extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected string $view = 'filament.pages.review-queue';

    protected static ?string $navigationLabel = 'Review';

    protected static ?string $title = 'Review Queue';

    protected static ?int $navigationSort = 1;

    public array $pendingApprovals = [];
    public array $selectedItems = [];

    public function mount(): void
    {
        $this->loadPendingApprovals();
    }

    public function loadPendingApprovals(): void
    {
        $service = app(ReviewQueueService::class);
        $this->pendingApprovals = $service->getPendingApprovals();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkApprove')
                ->label('Approve Selected')
                ->color('success')
                ->icon('heroicon-o-check')
                ->requiresConfirmation()
                ->disabled(fn () => empty($this->selectedItems))
                ->action(function () {
                    $this->bulkApprove();
                }),
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->loadPendingApprovals();
                    Notification::make()
                        ->title('Review queue refreshed')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function approveSingle(string $entityId, int $attributeId): void
    {
        try {
            $writer = app(EavWriter::class);
            $writer->approveVersioned($entityId, $attributeId);

            $this->loadPendingApprovals();

            Notification::make()
                ->title('Approved successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error approving attribute')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function bulkApprove(): void
    {
        if (empty($this->selectedItems)) {
            Notification::make()
                ->title('No items selected')
                ->warning()
                ->send();
            return;
        }

        try {
            $writer = app(EavWriter::class);
            $items = [];

            foreach ($this->selectedItems as $key => $selected) {
                if ($selected) {
                    // Key format: "entityId_attributeId"
                    [$entityId, $attributeId] = explode('_', $key);
                    $items[] = [
                        'entity_id' => $entityId,
                        'attribute_id' => (int) $attributeId,
                    ];
                }
            }

            $writer->bulkApprove($items);

            $this->selectedItems = [];
            $this->loadPendingApprovals();

            Notification::make()
                ->title('Approved ' . count($items) . ' attribute(s)')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error during bulk approval')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleSelection(string $entityId, int $attributeId): void
    {
        $key = "{$entityId}_{$attributeId}";
        $this->selectedItems[$key] = !($this->selectedItems[$key] ?? false);
    }

    public function toggleAllForEntity(string $entityId): void
    {
        // Find all attributes for this entity
        foreach ($this->pendingApprovals as $entity) {
            if ($entity['entity_id'] === $entityId) {
                $allSelected = true;
                foreach ($entity['attributes'] as $attr) {
                    $key = "{$entityId}_{$attr['attribute_id']}";
                    if (!($this->selectedItems[$key] ?? false)) {
                        $allSelected = false;
                        break;
                    }
                }

                // Toggle all
                foreach ($entity['attributes'] as $attr) {
                    $key = "{$entityId}_{$attr['attribute_id']}";
                    $this->selectedItems[$key] = !$allSelected;
                }
                break;
            }
        }
    }

    public function isSelected(string $entityId, int $attributeId): bool
    {
        $key = "{$entityId}_{$attributeId}";
        return $this->selectedItems[$key] ?? false;
    }
}

