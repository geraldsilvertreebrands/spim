<?php

namespace App\Jobs\Sync;

use App\Models\EntityType;
use App\Models\SyncRun;
use App\Services\MagentoApiClient;
use App\Services\Sync\AttributeOptionSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAttributeOptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            'sync_type' => 'options',
            'started_at' => now(),
            'status' => 'running',
            'triggered_by' => $this->triggeredBy,
            'user_id' => $this->userId,
        ]);

        try {
            $sync = app(AttributeOptionSync::class, [
                'entityType' => $this->entityType,
                'syncRun' => $syncRun, // Pass sync run to service
            ]);

            $result = $sync->sync();
            $stats = $result['stats'];

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
            Log::error('Attribute option sync job failed', [
                'entity_type' => $this->entityType->name,
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
        return ['sync', 'options', "entity-type:{$this->entityType->id}"];
    }
}
