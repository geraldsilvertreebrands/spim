<?php

namespace App\Services\Sync;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\SyncResult;
use App\Models\SyncRun;
use App\Services\MagentoApiClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttributeOptionSync extends AbstractSync
{
    private EntityType $entityType;
    private ?SyncRun $syncRun;

    public function __construct(
        MagentoApiClient $magentoClient,
        EntityType $entityType,
        ?SyncRun $syncRun = null
    ) {
        parent::__construct($magentoClient);
        $this->entityType = $entityType;
        $this->syncRun = $syncRun;
    }

    /**
     * Sync options for a single attribute
     * Public method for syncing individual attributes
     */
    public function syncSingleAttribute(Attribute $attribute): void
    {
        $this->syncAttributeOptions($attribute);
    }

    /**
     * Sync attribute options bi-directionally between SPIM and Magento
     * Uses Magento as source of truth for conflicts
     *
     * @return array Stats
     */
    public function sync(): array
    {
        $this->startSync("attribute option sync for {$this->entityType->name}");

        // Get all synced select/multiselect attributes for this entity type
        $attributes = Attribute::where('entity_type_id', $this->entityType->id)
            ->whereIn('is_sync', ['from_external', 'to_external'])
            ->whereIn('data_type', ['select', 'multiselect'])
            ->get();

        if ($attributes->isEmpty()) {
            $this->logInfo('No select/multiselect attributes marked for sync');
            return ['stats' => $this->stats];
        }

        foreach ($attributes as $attribute) {
            try {
                $this->syncAttributeOptions($attribute);
            } catch (\Exception $e) {
                $this->stats['errors']++;
                if ($this->syncRun) {
                    $this->syncRun->incrementError();
                }
                $this->logError("Failed to sync options for attribute {$attribute->name}", [
                    'attribute' => $attribute->name,
                    'error' => $e->getMessage(),
                ]);

                // Log to database if sync run exists
                $this->logResult($attribute, 'error', $e->getMessage());
            }
        }

        $this->completeSync("attribute option sync for {$this->entityType->name}");

        return ['stats' => $this->stats];
    }

    /**
     * Sync options for a single attribute
     * Uses Magento as source of truth - overwrites SPIM options with Magento values
     */
    private function syncAttributeOptions(Attribute $attribute): void
    {
        $this->logDebug("Syncing options for attribute: {$attribute->name}");

        // Get options from both systems
        $spimOptions = $attribute->allowedValues(); // Returns array: ['key' => 'label', ...]
        $magentoOptions = $this->magentoClient->getAttributeOptions($attribute->name);

        // Convert Magento options to associative array: ['value' => 'label', ...]
        $magentoOptionsMap = [];
        foreach ($magentoOptions as $option) {
            if (!empty($option['value'])) {
                $magentoOptionsMap[$option['value']] = $option['label'];
            }
        }

        $this->logDebug("Found options", [
            'attribute' => $attribute->name,
            'spim_count' => count($spimOptions),
            'magento_count' => count($magentoOptionsMap),
        ]);

        // Use Magento as source of truth: replace SPIM options entirely with Magento options
        if ($magentoOptionsMap != $spimOptions) {
            $this->replaceSpimOptions($attribute, $magentoOptionsMap);
        } else {
            $this->stats['skipped']++;
            if ($this->syncRun) {
                $this->syncRun->incrementSkipped();
            }
            $this->logResult($attribute, 'success', 'Options already in sync', 'skip');
        }
    }

    /**
     * Replace SPIM options entirely with Magento options
     * Magento is the source of truth
     */
    private function replaceSpimOptions(Attribute $attribute, array $magentoOptions): void
    {
        $oldOptions = $attribute->allowedValues();

        DB::table('attributes')
            ->where('id', $attribute->id)
            ->update([
                'allowed_values' => json_encode($magentoOptions),
                'updated_at' => now(),
            ]);

        $this->stats['updated']++;
        if ($this->syncRun) {
            $this->syncRun->incrementSuccess();
        }
        $this->logInfo("Replaced SPIM options with Magento options", [
            'attribute' => $attribute->name,
            'old_count' => count($oldOptions),
            'new_count' => count($magentoOptions),
        ]);

        $this->logResult(
            $attribute,
            'success',
            'Synced ' . count($magentoOptions) . ' options from Magento',
            'update',
            [
                'old_options' => $oldOptions,
                'new_options' => $magentoOptions,
            ]
        );
    }

    /**
     * Log result to database (if sync run exists)
     */
    private function logResult(
        Attribute $attribute,
        string $status,
        ?string $message = null,
        ?string $operation = null,
        ?array $details = null
    ): void {
        if (!$this->syncRun) {
            return; // No database logging if sync run not provided
        }

        SyncResult::create([
            'sync_run_id' => $this->syncRun->id,
            'attribute_id' => $attribute->id,
            'item_identifier' => $attribute->name,
            'operation' => $operation,
            'status' => $status,
            'error_message' => $message,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}



