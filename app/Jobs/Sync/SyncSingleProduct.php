<?php

namespace App\Jobs\Sync;

use App\Models\Entity;
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

    public function __construct(
        public Entity $entity,
        public ?int $userId = null,
        public string $triggeredBy = 'user'
    ) {}

    public function handle(): void
    {
        // Create sync run record
        $syncRun = SyncRun::create([
            'entity_type_id' => $this->entity->entity_type_id,
            'sync_type' => 'products',
            'started_at' => now(),
            'status' => 'running',
            'triggered_by' => $this->triggeredBy,
            'user_id' => $this->userId,
        ]);

        try {
            $sync = app(ProductSync::class, [
                'entityType' => $this->entity->entityType,
                'sku' => $this->entity->entity_id, // Sync specific product
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
                'entity_id' => $this->entity->id,
                'sku' => $this->entity->entity_id,
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
        return ['sync', 'products', "entity:{$this->entity->id}"];
    }
}
