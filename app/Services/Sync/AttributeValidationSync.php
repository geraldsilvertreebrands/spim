<?php

namespace App\Services\Sync;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\SyncResult;
use App\Models\SyncRun;
use App\Services\MagentoApiClient;
use Illuminate\Support\Facades\DB;

class AttributeValidationSync extends AbstractSync
{
    private EntityType $entityType;

    private ?SyncRun $syncRun;

    private array $validationResults = [];

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
     * Validate and sync attributes
     * - For select/multiselect: sync options
     * - For all types: validate type compatibility
     *
     * @return array Stats and validation results
     */
    public function sync(): array
    {
        $this->startSync("attribute validation and sync for {$this->entityType->name}");

        // Get all synced attributes for this entity type
        $attributes = Attribute::where('entity_type_id', $this->entityType->id)
            ->whereIn('is_sync', ['from_external', 'to_external'])
            ->get();

        if ($attributes->isEmpty()) {
            $this->logInfo('No attributes marked for sync');
            $this->validationResults['summary'] = 'No attributes are marked for sync';

            return [
                'stats' => $this->stats,
                'validation_results' => $this->validationResults,
            ];
        }

        $typeCheckResults = [];
        $optionSyncResults = [];

        foreach ($attributes as $attribute) {
            try {
                // Check type compatibility for all attributes
                $typeCheck = $this->validateAttributeType($attribute);
                $typeCheckResults[] = $typeCheck;

                // Sync options for select/multiselect
                if (in_array($attribute->data_type, ['select', 'multiselect'])) {
                    $optionSync = $this->syncAttributeOptions($attribute);
                    $optionSyncResults[] = $optionSync;
                }

                $this->stats['updated']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->logError("Failed to validate/sync attribute {$attribute->name}", [
                    'attribute' => $attribute->name,
                    'error' => $e->getMessage(),
                ]);

                $typeCheckResults[] = [
                    'attribute' => $attribute->name,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];

                $this->logResult($attribute, 'error', $e->getMessage());
            }
        }

        $this->validationResults = [
            'type_checks' => $typeCheckResults,
            'option_syncs' => $optionSyncResults,
            'summary' => $this->generateSummary($typeCheckResults, $optionSyncResults),
        ];

        $this->completeSync("attribute validation and sync for {$this->entityType->name}");

        return [
            'stats' => $this->stats,
            'validation_results' => $this->validationResults,
        ];
    }

    /**
     * Validate attribute type compatibility with Magento
     */
    private function validateAttributeType(Attribute $attribute): array
    {
        try {
            $magentoAttr = $this->magentoClient->getAttribute($attribute->name);
            $magentoType = $magentoAttr['frontend_input'] ?? $magentoAttr['backend_type'] ?? 'unknown';

            $compatibility = $this->checkTypeCompatibility($attribute->data_type, $magentoType);

            $result = [
                'attribute' => $attribute->name,
                'spim_type' => $attribute->data_type,
                'magento_type' => $magentoType,
                'status' => $compatibility,
            ];

            if ($compatibility === 'incompatible') {
                $result['message'] = "Type mismatch: SPIM '{$attribute->data_type}' vs Magento '{$magentoType}'";
                $this->logWarning($result['message']);
            } elseif ($compatibility === 'warning') {
                $result['message'] = "Potential issue: SPIM '{$attribute->data_type}' with Magento '{$magentoType}'";
                $this->logWarning($result['message']);
            } else {
                $result['message'] = 'Compatible';
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'attribute' => $attribute->name,
                'status' => 'error',
                'message' => "Failed to fetch from Magento: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Sync options for select/multiselect attribute
     */
    private function syncAttributeOptions(Attribute $attribute): array
    {
        try {
            $spimOptions = $attribute->allowedValues();
            $magentoOptions = $this->magentoClient->getAttributeOptions($attribute->name);

            // Convert Magento options to associative array
            $magentoOptionsMap = [];
            foreach ($magentoOptions as $option) {
                if (! empty($option['value'])) {
                    $magentoOptionsMap[$option['value']] = $option['label'];
                }
            }

            $result = [
                'attribute' => $attribute->name,
                'spim_count' => count($spimOptions),
                'magento_count' => count($magentoOptionsMap),
            ];

            // Use Magento as source of truth
            if ($magentoOptionsMap != $spimOptions) {
                DB::table('attributes')
                    ->where('id', $attribute->id)
                    ->update([
                        'allowed_values' => json_encode($magentoOptionsMap),
                        'updated_at' => now(),
                    ]);

                $result['status'] = 'synced';
                $result['message'] = "Synced {$result['magento_count']} options from Magento";
                $this->logInfo($result['message'], ['attribute' => $attribute->name]);
            } else {
                $result['status'] = 'unchanged';
                $result['message'] = 'Options already in sync';
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'attribute' => $attribute->name,
                'status' => 'error',
                'message' => "Failed to sync options: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Check type compatibility (same as ProductSync)
     */
    private function checkTypeCompatibility(string $spimType, string $magentoType): string
    {
        $compatibilityMap = [
            'integer' => ['int', 'boolean', 'static', 'decimal'],
            'text' => ['text', 'textarea', 'date', 'datetime', 'static', 'varchar', 'decimal', 'price'],
            'html' => ['textarea', 'text', 'static', 'varchar'],
            'json' => ['textarea', 'text', 'static', 'varchar'],
            'select' => ['select', 'boolean'],
            'multiselect' => ['multiselect'],
        ];

        $warningMap = [
            'integer' => ['decimal', 'price'],
            'text' => ['decimal', 'price'],
            'html' => ['text'],
        ];

        $magentoType = strtolower($magentoType);

        if (isset($compatibilityMap[$spimType])) {
            foreach ($compatibilityMap[$spimType] as $compatibleMagentoType) {
                if (str_contains($magentoType, $compatibleMagentoType)) {
                    if (isset($warningMap[$spimType]) && in_array($compatibleMagentoType, $warningMap[$spimType])) {
                        return 'warning';
                    }

                    return 'compatible';
                }
            }
        }

        return 'incompatible';
    }

    /**
     * Generate a human-readable summary
     */
    private function generateSummary(array $typeCheckResults, array $optionSyncResults): string
    {
        $compatible = 0;
        $warnings = 0;
        $incompatible = 0;
        $errors = 0;

        foreach ($typeCheckResults as $result) {
            switch ($result['status']) {
                case 'compatible':
                    $compatible++;
                    break;
                case 'warning':
                    $warnings++;
                    break;
                case 'incompatible':
                    $incompatible++;
                    break;
                case 'error':
                    $errors++;
                    break;
            }
        }

        $optionsSynced = 0;
        $optionsUnchanged = 0;
        foreach ($optionSyncResults as $result) {
            if ($result['status'] === 'synced') {
                $optionsSynced++;
            } elseif ($result['status'] === 'unchanged') {
                $optionsUnchanged++;
            }
        }

        $summary = 'Checked '.count($typeCheckResults).' attribute(s): ';
        $summary .= "{$compatible} compatible, {$warnings} warning(s), {$incompatible} incompatible, {$errors} error(s). ";

        if (! empty($optionSyncResults)) {
            $summary .= "Options: {$optionsSynced} synced, {$optionsUnchanged} unchanged.";
        }

        return $summary;
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
        if (! $this->syncRun) {
            return;
        }

        SyncResult::create([
            'sync_run_id' => $this->syncRun->id,
            'attribute_id' => $attribute->id,
            'item_identifier' => $attribute->name,
            'operation' => $operation ?? 'validate',
            'status' => $status,
            'error_message' => $message,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
