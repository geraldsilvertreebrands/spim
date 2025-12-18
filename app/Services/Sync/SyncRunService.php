<?php

namespace App\Services\Sync;

use App\Models\EntityType;
use App\Models\SyncRun;

class SyncRunService
{
    /**
     * Wrap a sync execution in a SyncRun lifecycle and return the SyncRun.
     *
     * $runner receives the created SyncRun and must return an array of stats with keys:
     * - created, updated, errors, skipped (all optional, default 0)
     */
    public function run(
        string $syncType,
        EntityType $entityType,
        ?int $userId,
        string $triggeredBy,
        callable $runner
    ): SyncRun {
        $syncRun = SyncRun::create([
            'entity_type_id' => $entityType->id,
            'sync_type' => $syncType,
            'started_at' => now(),
            'status' => 'running',
            'triggered_by' => $triggeredBy,
            'user_id' => $userId,
        ]);

        try {
            $result = $runner($syncRun);
            $stats = is_array($result) ? $result : [];

            $created = (int) ($stats['created'] ?? 0);
            $updated = (int) ($stats['updated'] ?? 0);
            $errors = (int) ($stats['errors'] ?? 0);
            $skipped = (int) ($stats['skipped'] ?? 0);

            $syncRun->update([
                'completed_at' => now(),
                'status' => $errors > 0 ? 'partial' : 'completed',
                'total_items' => $created + $updated + $errors + $skipped,
                'successful_items' => $created + $updated,
                'failed_items' => $errors,
                'skipped_items' => $skipped,
            ]);
        } catch (\Throwable $e) {
            $syncRun->markFailed($e->getMessage());
            throw $e;
        }

        return $syncRun;
    }
}
