<?php

namespace App\Jobs\Sync;

use App\Models\EntityType;
use App\Models\SyncRun;
use App\Services\Sync\ProductSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAllProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour max

    public function __construct(
        public EntityType $entityType,
        public ?int $userId = null,
        public string $triggeredBy = 'cli'
    ) {}

    public function handle(): void
    {
        // Create sync run record
        $syncRun = SyncRun::create([
            'entity_type_id' => $this->entityType->id,
            'sync_type' => 'products',
            'started_at' => now(),
            'status' => 'running',
            'triggered_by' => $this->triggeredBy,
            'user_id' => $this->userId,
        ]);

        try {
            $sync = app(ProductSync::class, [
                'entityType' => $this->entityType,
                'sku' => null, // Sync all products
                'syncRun' => $syncRun, // Pass sync run to service
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
            Log::error('Product sync job failed', [
                'entity_type' => $this->entityType->name,
                'error' => $e->getMessage(),
            ]);
            // Don't re-throw - let the job complete so sync run status is persisted
        }
    }

    /**
     * Get tags for queue monitoring
     */
    public function tags(): array
    {
        return ['sync', 'products', "entity-type:{$this->entityType->id}"];
    }
}
