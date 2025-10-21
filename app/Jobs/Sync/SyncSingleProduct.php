<?php

namespace App\Jobs\Sync;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\SyncRun;
use App\Services\Sync\ProductSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSingleProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * Can be called with either:
     * 1. An Entity model (for syncing existing entities in SPIM)
     * 2. EntityType + entity ID string (for importing from Magento)
     */
    public function __construct(
        public Entity|EntityType $entityOrType,
        public ?string $entityId = null,
        public ?int $userId = null,
        public string $triggeredBy = 'user'
    ) {}

    public function handle(): void
    {
        // Determine entity type and entity ID based on what was passed
        if ($this->entityOrType instanceof Entity) {
            $entityType = $this->entityOrType->entityType;
            $entityId = $this->entityOrType->entity_id;
            $entityModelId = $this->entityOrType->id;
        } else {
            $entityType = $this->entityOrType;
            $entityId = $this->entityId;
            $entityModelId = null;
        }

        // Create sync run record
        $syncRun = SyncRun::create([
            'entity_type_id' => $entityType->id,
            'sync_type' => 'products',
            'started_at' => now(),
            'status' => 'running',
            'triggered_by' => $this->triggeredBy,
            'user_id' => $this->userId,
        ]);

        try {
            $sync = app(ProductSync::class, [
                'entityType' => $entityType,
                'sku' => $entityId, // Sync specific product by entity_id/SKU
                'syncRun' => $syncRun,
            ]);

            $stats = $sync->sync();

            // Update sync run with results
            $syncRun->update([
                'completed_at' => now(),
                'status' => $stats['errors'] > 0 ? 'partial' : 'completed',
                'total_items' => $stats['created'] + $stats['updated'] + $stats['errors'] + $stats['skipped'],
                'successful_items' => $stats['created'] + $stats['updated'],
                'failed_items' => $stats['errors'],
                'skipped_items' => $stats['skipped'],
            ]);

        } catch (\Exception $e) {
            $syncRun->markFailed($e->getMessage());
            Log::error('Single product sync job failed', [
                'entity_type_id' => $entityType->id,
                'entity_id' => $entityId,
                'entity_model_id' => $entityModelId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get tags for queue monitoring
     */
    public function tags(): array
    {
        $entityId = $this->entityOrType instanceof Entity
            ? $this->entityOrType->entity_id
            : $this->entityId;

        return ['sync', 'products', "entity_id:{$entityId}"];
    }
}
