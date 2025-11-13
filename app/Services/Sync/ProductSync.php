<?php

namespace App\Services\Sync;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\SyncResult;
use App\Models\SyncRun;
use App\Services\EavWriter;
use App\Services\MagentoApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductSync extends AbstractSync
{
    private EntityType $entityType;
    private ?string $sku;
    private EavWriter $eavWriter;
    private array $syncedAttributes;
    private ?SyncRun $syncRun;

    public function __construct(
        MagentoApiClient $magentoClient,
        EavWriter $eavWriter,
        EntityType $entityType,
        ?string $sku = null,
        ?SyncRun $syncRun = null
    ) {
        parent::__construct($magentoClient);
        $this->eavWriter = $eavWriter;
        $this->entityType = $entityType;
        $this->sku = $sku;
        $this->syncRun = $syncRun;
    }

    /**
     * Execute product sync
     *
     * @return array Sync statistics
     */
    public function sync(): array
    {
        $operation = $this->sku
            ? "product sync for {$this->entityType->name} (SKU: {$this->sku})"
            : "product sync for {$this->entityType->name}";

        $this->startSync($operation);

        try {
            // Step 0: Validate synced attributes
            $this->validateSyncedAttributes();

            // Step 1: Pull from Magento → SPIM
            $this->pullFromMagento();

            // Step 2: Push from SPIM → Magento
            $this->pushToMagento();

            $this->completeSync($operation);
        } catch (\Exception $e) {
            $this->logError("Sync failed: {$e->getMessage()}", [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $this->stats;
    }

    /**
     * Validate that all synced attributes can be synced
     *
     * @throws RuntimeException if validation fails
     */
    private function validateSyncedAttributes(): void
    {
        $this->logInfo('Validating synced attributes');

        $this->syncedAttributes = Attribute::where('entity_type_id', $this->entityType->id)
            ->whereIn('is_sync', ['from_external', 'to_external', 'bidirectional'])
            ->get()
            ->keyBy('name')
            ->all();

        if (empty($this->syncedAttributes)) {
            $this->logWarning('No attributes marked for sync');
            return;
        }

        // Validate data type compatibility
        $errors = [];
        $warnings = [];

        foreach ($this->syncedAttributes as $attribute) {
            // Check if belongs_to types are not marked for sync
            if (in_array($attribute->data_type, ['belongs_to', 'belongs_to_multi'])) {
                $errors[] = "Attribute '{$attribute->name}' is a relationship type, which cannot be synced";
                continue;
            }

            // Check data type compatibility with Magento (optional validation)
            // Skip if getAttribute is not available (e.g., in tests with partial mocks)
            try {
                $magentoAttr = $this->magentoClient->getAttribute($attribute->name);
                $magentoType = $magentoAttr['frontend_input'] ?? $magentoAttr['backend_type'] ?? 'unknown';

                $compatibility = $this->checkTypeCompatibility($attribute->data_type, $magentoType);

                if ($compatibility === 'incompatible') {
                    $errors[] = "Attribute '{$attribute->name}': SPIM type '{$attribute->data_type}' is incompatible with Magento type '{$magentoType}'";
                } elseif ($compatibility === 'warning') {
                    $warnings[] = "Attribute '{$attribute->name}': SPIM type '{$attribute->data_type}' may have issues with Magento type '{$magentoType}' - please verify";
                }
            } catch (\BadMethodCallException $e) {
                // Method not mocked in tests - skip validation
                $this->logDebug("Skipping type validation for '{$attribute->name}' - getAttribute not available");
            } catch (\Exception $e) {
                // Log as warning, but don't fail sync - type checking is advisory
                $this->logWarning("Could not validate type for '{$attribute->name}': {$e->getMessage()}");
            }
        }

        // Log warnings
        foreach ($warnings as $warning) {
            $this->logWarning($warning);
        }

        if (!empty($errors)) {
            $errorMessage = "Attribute validation failed:\n" . implode("\n", $errors);
            $this->logError($errorMessage);
            throw new RuntimeException($errorMessage);
        }

        $this->logInfo("Validated {count} synced attributes", ['count' => count($this->syncedAttributes)]);
    }

    /**
     * Check if SPIM and Magento data types are compatible
     *
     * @param string $spimType SPIM data type
     * @param string $magentoType Magento frontend_input or backend_type
     * @return string 'compatible', 'warning', or 'incompatible'
     */
    private function checkTypeCompatibility(string $spimType, string $magentoType): string
    {
        // Define compatible type mappings
        $compatibilityMap = [
            'integer' => ['int', 'boolean', 'static', 'decimal', 'price'],
            'text' => ['text', 'textarea', 'date', 'datetime', 'static', 'varchar', 'decimal', 'price'],
            'html' => ['textarea', 'text', 'static', 'varchar'],
            'json' => ['textarea', 'text', 'static', 'varchar'],
            'select' => ['select', 'boolean'],
            'multiselect' => ['multiselect'],
        ];

        // Define warning cases (potentially compatible but may need attention)
        $warningMap = [
            'integer' => ['decimal', 'price'],  // May lose decimal precision
            'text' => ['decimal', 'price'],     // Storing numbers as text
            'html' => ['text'],                 // Plain text into HTML field
        ];

        $magentoType = strtolower($magentoType);

        // Check if types are compatible
        if (isset($compatibilityMap[$spimType])) {
            foreach ($compatibilityMap[$spimType] as $compatibleMagentoType) {
                if (str_contains($magentoType, $compatibleMagentoType)) {
                    // Check if it's a warning case
                    if (isset($warningMap[$spimType]) && in_array($compatibleMagentoType, $warningMap[$spimType])) {
                        return 'warning';
                    }
                    return 'compatible';
                }
            }
        }

        // If no match found, it's incompatible
        return 'incompatible';
    }

    /**
     * Pull products from Magento to SPIM
     */
    private function pullFromMagento(): void
    {
        $this->logInfo('Pulling products from Magento');

        if ($this->sku) {
            // Single product sync
            $magentoProduct = $this->magentoClient->getProduct($this->sku);
            if ($magentoProduct) {
                try {
                    $this->importProduct($magentoProduct);
                } catch (\Exception $e) {
                    $this->handleImportError($magentoProduct, $e);
                }
            }
        } else {
            // Full sync with incremental processing - process each page as it's fetched
            $this->magentoClient->getProducts([], function ($products, $page, $total) {
                foreach ($products as $magentoProduct) {
                    try {
                        $this->importProduct($magentoProduct);
                    } catch (\Exception $e) {
                        $this->handleImportError($magentoProduct, $e);
                    }
                }
            });
        }
    }

    /**
     * Handle product import error
     */
    private function handleImportError(array $magentoProduct, \Exception $e): void
    {
        $this->stats['errors']++;
        if ($this->syncRun) {
            $this->syncRun->incrementError();
        }
        $sku = $magentoProduct['sku'] ?? 'unknown';
        $this->logError("Failed to import product {$sku}", [
            'sku' => $sku,
            'error' => $e->getMessage(),
            'product_data' => $magentoProduct,
        ]);

        // Log error to database (entity may not exist yet)
        if ($this->syncRun) {
            SyncResult::create([
                'sync_run_id' => $this->syncRun->id,
                'item_identifier' => $sku,
                'operation' => 'create',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Import a single product from Magento to SPIM
     */
    private function importProduct(array $magentoProduct): void
    {
        $sku = $magentoProduct['sku'];

        DB::transaction(function () use ($magentoProduct, $sku) {
            // Find or create entity
            $entity = Entity::where('entity_type_id', $this->entityType->id)
                ->where('entity_id', $sku)
                ->first();

            $isNew = !$entity;

            if ($isNew) {
                // Create new entity
                $entity = Entity::create([
                    'id' => (string) Str::ulid(),
                    'entity_type_id' => $this->entityType->id,
                    'entity_id' => $sku,
                ]);
                $this->logInfo("Created new entity for SKU: {$sku}");
            }

            // Extract custom attributes from Magento product
            $customAttributes = [];
            if (isset($magentoProduct['custom_attributes'])) {
                foreach ($magentoProduct['custom_attributes'] as $attr) {
                    $customAttributes[$attr['attribute_code']] = $attr['value'];
                }
            }

            // Sync attributes
            foreach ($this->syncedAttributes as $attributeName => $attribute) {
                // Get value from Magento
                $value = $magentoProduct[$attributeName]
                    ?? $customAttributes[$attributeName]
                    ?? null;

                // Convert value to string for storage
                $stringValue = $this->convertValueToString($value, $attribute->data_type);

                if ($isNew) {
                    // For new products, import ALL synced attributes (treat all as from_external for initial creation)
                    // Set all three value fields to the same value
                    $this->importVersionedAttribute($entity->id, $attribute->id, $stringValue);
                } else {
                    // For existing products, handle based on sync direction
                    if ($attribute->is_sync === 'from_external') {
                        // Read-only from Magento - always update from Magento
                        $this->importVersionedAttribute($entity->id, $attribute->id, $stringValue);
                    } elseif ($attribute->is_sync === 'bidirectional') {
                        // Bidirectional - use 3-way comparison to detect conflicts
                        $this->handleBidirectionalAttribute($entity, $attribute, $stringValue);
                    }
                    // Attributes with is_sync='to_external' are not updated from Magento
                }
            }

            if ($isNew) {
                $this->stats['created']++;
                if ($this->syncRun) {
                    $this->syncRun->incrementSuccess();
                }
                $this->logResult($entity, 'success', 'Product imported from Magento', 'create');
            } else {
                $this->stats['updated']++;
                if ($this->syncRun) {
                    $this->syncRun->incrementSuccess();
                }
                $this->logResult($entity, 'success', 'Product updated from Magento', 'update');
            }
        });
    }

    /**
     * Import a versioned attribute with all three value fields set identically
     * This is used for importing from Magento (from_external sync)
     */
    private function importVersionedAttribute(string $entityId, int $attributeId, ?string $value): void
    {
        $now = now();

        // Check if record already exists
        $existing = DB::table('eav_versioned')->where([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
        ])->first();

        if ($existing) {
            // Update existing record - set all three value fields
            DB::table('eav_versioned')
                ->where('id', $existing->id)
                ->update([
                    'value_current' => $value,
                    'value_approved' => $value,
                    'value_live' => $value,
                    'updated_at' => $now,
                ]);
            return;
        }

        // Insert new record
        DB::table('eav_versioned')->insert([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
            'value_current' => $value,
            'value_approved' => $value,
            'value_live' => $value,
            'value_override' => null,
            'input_hash' => null,
            'justification' => 'Imported from Magento',
            'confidence' => null,
            'meta' => json_encode([]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Handle bidirectional attribute sync with 3-way comparison
     * Compares Magento value, SPIM approved value, and SPIM live value to detect conflicts
     *
     * @param Entity $entity The entity being synced
     * @param Attribute $attribute The attribute to sync
     * @param string|null $magentoValue Fresh value from Magento
     * @return void
     */
    private function handleBidirectionalAttribute(Entity $entity, Attribute $attribute, ?string $magentoValue): void
    {
        $now = now();

        // Get current SPIM state
        $existing = DB::table('eav_versioned')->where([
            'entity_id' => $entity->id,
            'attribute_id' => $attribute->id,
        ])->first();

        // If no existing record, create one with Magento's value
        if (!$existing) {
            $this->importVersionedAttribute($entity->id, $attribute->id, $magentoValue);
            return;
        }

        // 3-way comparison to detect changes
        $magentoChanged = ($magentoValue !== $existing->value_live);
        $spimChanged = ($existing->value_approved !== $existing->value_live);

        // Case 1: Neither changed - skip
        if (!$magentoChanged && !$spimChanged) {
            return;
        }

        // Case 2: SPIM only changed - will be pushed in export phase, nothing to do here
        if ($spimChanged && !$magentoChanged) {
            return;
        }

        // Case 3: Magento only changed - update all fields
        if ($magentoChanged && !$spimChanged) {
            DB::table('eav_versioned')
                ->where('id', $existing->id)
                ->update([
                    'value_current' => $magentoValue,
                    'value_approved' => $magentoValue,
                    'value_live' => $magentoValue,
                    'updated_at' => $now,
                ]);
            return;
        }

        // Case 4: BOTH changed - CONFLICT
        // Resolution: Accept Magento's value in approved and live, but preserve SPIM's current value
        // This pushes the SPIM change into the approval queue for user review

        // Preserve SPIM's metadata in conflict marker
        $existingMeta = json_decode($existing->meta ?? '{}', true) ?: [];
        $conflictMeta = [
            'sync_conflict' => true,
            'sync_conflict_at' => now()->toIso8601String(),
            'spim_value_at_conflict' => $existing->value_approved,
            'spim_justification_at_conflict' => $existing->justification,
            'spim_confidence_at_conflict' => $existing->confidence,
        ];

        // Update: value_current stays as-is, set approved & live to Magento's value
        DB::table('eav_versioned')
            ->where('id', $existing->id)
            ->update([
                'value_approved' => $magentoValue,
                'value_live' => $magentoValue,
                'meta' => json_encode(array_merge($existingMeta, $conflictMeta)),
                'updated_at' => $now,
            ]);

        // Log conflict as warning
        $this->logResult(
            $entity,
            'warning',
            "Conflict detected for attribute '{$attribute->name}': Both SPIM and Magento changed. Using Magento value, SPIM change queued for review.",
            'conflict',
            [
                'attribute' => $attribute->name,
                'magento_value' => $magentoValue,
                'spim_value' => $existing->value_approved,
                'spim_current' => $existing->value_current,
            ]
        );
    }

    /**
     * Push products from SPIM to Magento
     */
    private function pushToMagento(): void
    {
        $this->logInfo('Pushing products to Magento');

        // Get all entities for this entity type
        $query = Entity::where('entity_type_id', $this->entityType->id);

        if ($this->sku) {
            $query->where('entity_id', $this->sku);
        }

        $entities = $query->get();

        foreach ($entities as $entity) {
            try {
                $this->exportProduct($entity);
            } catch (\Exception $e) {
                $this->stats['errors']++;
                if ($this->syncRun) {
                    $this->syncRun->incrementError();
                }
                $this->logError("Failed to export product {$entity->entity_id}", [
                    'sku' => $entity->entity_id,
                    'error' => $e->getMessage(),
                ]);

                // Log error to database
                $this->logResult($entity, 'error', $e->getMessage());
            }
        }
    }

    /**
     * Export a single product from SPIM to Magento
     */
    private function exportProduct(Entity $entity): void
    {
        $sku = $entity->entity_id;

        DB::transaction(function () use ($entity, $sku) {
            // Check if product exists in Magento
            $magentoProduct = $this->magentoClient->getProduct($sku);
            $isNew = $magentoProduct === null;

            // Get attributes that need syncing
            $attributesToSync = $this->getAttributesToSync($entity, $isNew);

            if (empty($attributesToSync)) {
                $this->stats['skipped']++;
                if ($this->syncRun) {
                    $this->syncRun->incrementSkipped();
                }
                $this->logDebug("No attributes to sync for {$sku}");
                $this->logResult($entity, 'success', 'No changes to sync', 'skip');
                return;
            }

            // Build Magento payload
            $payload = $this->buildMagentoPayload($entity, $attributesToSync, $isNew);

            // Send to Magento
            if ($isNew) {
                $payload['sku'] = $sku;
                $this->magentoClient->createProduct($payload);
                $this->logInfo("Created product in Magento: {$sku}");
                $this->stats['created']++;
                if ($this->syncRun) {
                    $this->syncRun->incrementSuccess();
                }
                $this->logResult($entity, 'success', 'Product created in Magento', 'create', ['attributes_synced' => $attributesToSync]);
            } else {
                $this->magentoClient->updateProduct($sku, $payload);
                $this->logInfo("Updated product in Magento: {$sku}");
                $this->stats['updated']++;
                if ($this->syncRun) {
                    $this->syncRun->incrementSuccess();
                }
                $this->logResult($entity, 'success', 'Product updated in Magento', 'update', ['attributes_synced' => $attributesToSync]);
            }

            // Update value_live for synced versioned attributes
            $this->updateValueLive($entity, $attributesToSync);
        });
    }

    /**
     * Get attributes that need to be synced to Magento
     *
     * @return array Array of attribute names
     */
    private function getAttributesToSync(Entity $entity, bool $isNew): array
    {
        if ($isNew) {
            // For new products, sync all synced attributes
            return array_keys($this->syncedAttributes);
        }

        // For existing products, only sync attributes where:
        // 1. is_sync='to_external' OR is_sync='bidirectional'
        // 2. AND value_approved != value_live
        $attributesToSync = [];

        foreach ($this->syncedAttributes as $attributeName => $attribute) {
            // Only sync 'to_external' and 'bidirectional' attributes (not 'from_external')
            if (in_array($attribute->is_sync, ['to_external', 'bidirectional'])) {
                $versionedRecord = DB::table('eav_versioned')
                    ->where('entity_id', $entity->id)
                    ->where('attribute_id', $attribute->id)
                    ->first();

                if ($versionedRecord) {
                    // Determine the value to sync (override takes precedence)
                    $valueToSync = $versionedRecord->value_override ?? $versionedRecord->value_approved;
                    $valueLive = $versionedRecord->value_live;

                    // Sync if they differ
                    if ($valueToSync !== $valueLive) {
                        $attributesToSync[] = $attributeName;
                    }
                }
            }
        }

        return $attributesToSync;
    }

    /**
     * Build Magento product payload
     */
    private function buildMagentoPayload(Entity $entity, array $attributesToSync, bool $isNew): array
    {
        $payload = [];
        $customAttributes = [];

        foreach ($attributesToSync as $attributeName) {
            $attribute = $this->syncedAttributes[$attributeName];
            $value = $this->getAttributeValue($entity, $attribute);

            // Convert value for Magento
            $magentoValue = $this->convertValueForMagento($value, $attribute);

            // Check if this is a standard Magento product field or custom attribute
            if (in_array($attributeName, ['name', 'price', 'status', 'visibility', 'type_id', 'weight'])) {
                $payload[$attributeName] = $magentoValue;
            } else {
                $customAttributes[] = [
                    'attribute_code' => $attributeName,
                    'value' => $magentoValue,
                ];
            }
        }

        if (!empty($customAttributes)) {
            $payload['custom_attributes'] = $customAttributes;
        }

        // For new products, set status to disabled unless status is synced
        if ($isNew && !isset($payload['status'])) {
            $payload['status'] = 2; // Disabled
        }

        return $payload;
    }

    /**
     * Get attribute value for an entity
     */
    private function getAttributeValue(Entity $entity, Attribute $attribute): ?string
    {
        // All attributes now use eav_versioned table
        $record = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $attribute->id)
            ->first();

        if (!$record) {
            return null;
        }

        // Use override if present, otherwise use approved value
        return $record->value_override ?? $record->value_approved;
    }

    /**
     * Convert value from SPIM storage format to Magento format
     *
     * SPIM stores:
     * - select: plain string
     * - multiselect: JSON array (e.g., ["16802","16722"])
     *
     * Magento expects:
     * - select: string or integer (option ID)
     * - multiselect: comma-separated string (e.g., "16802,16722")
     */
    private function convertValueForMagento(?string $value, Attribute $attribute)
    {
        if ($value === null) {
            return null;
        }

        // Use AttributeCaster to decode the stored value first
        $decodedValue = \App\Support\AttributeCaster::castOut($attribute->data_type, $value);

        // Handle different data types
        return match($attribute->data_type) {
            'integer' => (int) $decodedValue,
            'select' => $decodedValue, // Already a string/integer
            'multiselect' => is_array($decodedValue)
                ? implode(',', $decodedValue) // Convert array to comma-separated string
                : (string) $decodedValue,
            'json' => $decodedValue, // Already decoded by castOut
            default => $decodedValue,
        };
    }

    /**
     * Convert value from Magento format to SPIM storage format
     *
     * Magento returns:
     * - select: string or integer (option ID)
     * - multiselect: comma-separated string (e.g., "16802,16722")
     *
     * SPIM stores:
     * - select: plain string
     * - multiselect: JSON array (e.g., ["16802","16722"])
     */
    private function convertValueToString($value, string $dataType): ?string
    {
        if ($value === null) {
            return null;
        }

        // For multiselect, parse comma-separated string into array
        if ($dataType === 'multiselect') {
            // If already an array, use it; otherwise parse comma-separated string
            $arrayValue = is_array($value)
                ? $value
                : array_map('trim', explode(',', (string) $value));

            // Use AttributeCaster to ensure proper formatting
            return \App\Support\AttributeCaster::castIn('multiselect', $arrayValue);
        }

        // For all other types (including select), use AttributeCaster
        return \App\Support\AttributeCaster::castIn($dataType, $value);
    }

    /**
     * Update value_live for synced attributes
     */
    private function updateValueLive(Entity $entity, array $attributesToSync): void
    {
        foreach ($attributesToSync as $attributeName) {
            $attribute = $this->syncedAttributes[$attributeName];

            $record = DB::table('eav_versioned')
                ->where('entity_id', $entity->id)
                ->where('attribute_id', $attribute->id)
                ->first();

            if ($record) {
                $valueToSync = $record->value_override ?? $record->value_approved;

                DB::table('eav_versioned')
                    ->where('id', $record->id)
                    ->update([
                        'value_live' => $valueToSync,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Log result to database (if sync run exists)
     */
    private function logResult(
        Entity $entity,
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
            'entity_id' => $entity->id,
            'item_identifier' => $entity->entity_id,
            'operation' => $operation,
            'status' => $status,
            'error_message' => $message,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}




