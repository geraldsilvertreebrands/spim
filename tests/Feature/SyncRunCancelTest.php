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

    public function test_cancel_sync_fails_due_to_enum_constraint()
    {
        // This test documents the original issue that was fixed
        // The ENUM constraint previously prevented 'cancelled' from being set
        $this->markTestSkipped('This test documents the original issue that has been fixed by adding "cancelled" to the ENUM');
    }

    public function test_cancel_sync_works_after_enum_fix()
    {
        // This test now passes after fixing the migration

        // Create test data
        $entityType = EntityType::firstOrCreate(['name' => 'product'], [
            'name' => 'product',
            'description' => 'Product entity type',
        ]);

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
