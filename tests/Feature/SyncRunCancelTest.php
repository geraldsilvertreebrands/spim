<?php

namespace Tests\Feature;

use App\Models\EntityType;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncRunCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_sync_works()
    {
        // Test that sync runs can be cancelled

        // Create test data
        $entityType = EntityType::where('name', 'product')->firstOrFail();

        $user = User::factory()->create();

        // Create a running sync
        $syncRun = SyncRun::create([
            'entity_type_id' => $entityType->id,
            'sync_type' => 'options',
            'started_at' => now(),
            'status' => 'running',
            'triggered_by' => 'user',
            'user_id' => $user->id,
        ]);

        // Cancel the sync
        $syncRun->cancel();

        // Verify sync is cancelled
        $this->assertEquals('cancelled', $syncRun->fresh()->status);
        $this->assertNotNull($syncRun->fresh()->completed_at);
        $this->assertEquals('Sync was cancelled by user', $syncRun->fresh()->error_summary);
    }
}
