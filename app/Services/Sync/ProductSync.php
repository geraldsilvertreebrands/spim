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
            ->whereIn('is_sync', ['from_external', 'to_external'])
            ->get()
            ->keyBy('name')
            ->all();

        if (empty($this->syncedAttributes)) {
            $this->logWarning('No attributes marked for sync');
            return;
        }

        // Validate data type compatibility
        $errors = [];
        foreach ($this->syncedAttributes as $attribute) {
            // Check if belongs_to types are not marked for sync
            if (in_array($attribute->data_type, ['belongs_to', 'belongs_to_multi'])) {
                $errors[] = "Attribute '{$attribute->name}' is a relationship type, which cannot be synced";
            }
        }

        if (!empty($errors)) {
            $errorMessage = "Attribute validation failed:\n" . implode("\n", $errors);
            $this->logError($errorMessage);
            throw new RuntimeException($errorMessage);
        }

        $this->logInfo("Validated {count} synced attributes", ['count' => count($this->syncedAttributes)]);
    }

    /**
     * Pull products from Magento to SPIM
     */
    private function pullFromMagento(): void
    {
        $this->logInfo('Pulling products from Magento');

        // Get products from Magento
        $magentoProducts = $this->sku
            ? [$this->magentoClient->getProduct($this->sku)]
            : ($this->magentoClient->getProducts()['items'] ?? []);

        $magentoProducts = array_filter($magentoProducts); // Remove nulls from failed fetches

        foreach ($magentoProducts as $magentoProduct) {
            try {
                $this->importProduct($magentoProduct);
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->logError("Failed to import product {$magentoProduct['sku']}", [
                    'sku' => $magentoProduct['sku'],
                    'error' => $e->getMessage(),
                ]);

                // Log error to database (entity may not exist yet)
                if ($this->syncRun) {
                    SyncResult::create([
                        'sync_run_id' => $this->syncRun->id,
                        'item_identifier' => $magentoProduct['sku'],
                        'operation' => 'create',
                        'status' => 'error',
                        'error_message' => $e->getMessage(),
                        'created_at' => now(),
                    ]);
                }
            }
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
                    // For existing products, only update attributes with is_sync='from_external'
                    if ($attribute->is_sync === 'from_external') {
                        $this->importVersionedAttribute($entity->id, $attribute->id, $stringValue);
                    }
                    // Attributes with is_sync='to_external' are not updated from Magento
                }
            }

            if ($isNew) {
                $this->stats['created']++;
                $this->logResult($entity, 'success', 'Product imported from Magento', 'create');
            } else {
                $this->stats['updated']++;
                $this->logResult($entity, 'success', 'Product updated from Magento', 'update');
            }
        });
    }

    /**
     * Import a versioned attribute with all three value fields set identically
     * This is used for initial import only
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
            return; // Don't overwrite existing versioned data
        }

        DB::table('eav_versioned')->insert([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
            'value_current' => $value,
            'value_approved' => $value,
            'value_live' => $value,
            'value_override' => null,
            'input_hash' => null,
            'justification' => 'Initial import from Magento',
            'confidence' => null,
            'meta' => json_encode([]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
                $this->logResult($entity, 'success', 'Product created in Magento', 'create', ['attributes_synced' => $attributesToSync]);
            } else {
                $this->magentoClient->updateProduct($sku, $payload);
                $this->logInfo("Updated product in Magento: {$sku}");
                $this->stats['updated']++;
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
        // 1. is_sync='to_external'
        // 2. AND value_approved != value_live
        $attributesToSync = [];

        foreach ($this->syncedAttributes as $attributeName => $attribute) {
            // Only sync 'to_external' attributes (not 'from_external')
            if ($attribute->is_sync === 'to_external') {
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
     * Convert value from SPIM to Magento format
     */
    private function convertValueForMagento(?string $value, Attribute $attribute)
    {
        if ($value === null) {
            return null;
        }

        // Handle different data types
        return match($attribute->data_type) {
            'integer' => (int) $value,
            'select', 'multiselect' => $value, // Option IDs are already stored
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Convert value to string for storage
     */
    private function convertValueToString($value, string $dataType): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
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




