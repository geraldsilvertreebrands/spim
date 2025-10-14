<?php

namespace Tests\Feature;

use App\Jobs\Sync\SyncAllProducts;
use App\Jobs\Sync\SyncAttributeOptions;
use App\Jobs\Sync\SyncSingleProduct;
use App\Models\Entity;
use App\Models\EntityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagentoSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private EntityType $entityType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product', 'description' => 'Test product type']
        );
    }

    #[Test]
    public function test_sync_magento_options_command(): void
    {
        Queue::fake();

        $this->artisan('sync:magento:options', ['entityType' => 'product'])
            ->expectsOutput('Attribute option sync for product queued.')
            ->assertExitCode(0);

        Queue::assertPushed(SyncAttributeOptions::class, function ($job) {
            return $job->entityType->name === 'product' &&
                   $job->triggeredBy === 'schedule';
        });
    }

    #[Test]
    public function test_sync_magento_command_without_sku(): void
    {
        Queue::fake();

        $this->artisan('sync:magento', ['entityType' => 'product'])
            ->expectsOutput('Full product sync for product queued.')
            ->assertExitCode(0);

        Queue::assertPushed(SyncAllProducts::class, function ($job) {
            return $job->entityType->name === 'product' &&
                   $job->triggeredBy === 'schedule';
        });
    }

    #[Test]
    public function test_sync_magento_command_with_sku(): void
    {
        Queue::fake();

        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-SKU-001',
        ]);

        $this->artisan('sync:magento', [
            'entityType' => 'product',
            '--sku' => 'TEST-SKU-001',
        ])
            ->expectsOutput('Product sync for SKU TEST-SKU-001 queued.')
            ->assertExitCode(0);

        Queue::assertPushed(SyncSingleProduct::class, function ($job) use ($entity) {
            return $job->entity->id === $entity->id &&
                   $job->triggeredBy === 'schedule';
        });
    }

    #[Test]
    public function test_commands_fail_with_invalid_entity_type(): void
    {
        $this->artisan('sync:magento:options', ['entityType' => 'invalid'])
            ->assertFailed();

        $this->artisan('sync:magento', ['entityType' => 'invalid'])
            ->assertFailed();
    }

    #[Test]
    public function test_sync_command_fails_with_invalid_sku(): void
    {
        $this->artisan('sync:magento', [
            'entityType' => 'product',
            '--sku' => 'NON-EXISTENT-SKU',
        ])
            ->assertFailed();
    }

    #[Test]
    public function test_commands_queue_jobs_properly(): void
    {
        Queue::fake();

        // Run options command
        $this->artisan('sync:magento:options', ['entityType' => 'product']);

        // Run full sync command
        $this->artisan('sync:magento', ['entityType' => 'product']);

        // Run single product sync command
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'SKU-123',
        ]);
        $this->artisan('sync:magento', [
            'entityType' => 'product',
            '--sku' => 'SKU-123',
        ]);

        // Verify all jobs were queued
        Queue::assertPushed(SyncAttributeOptions::class, 1);
        Queue::assertPushed(SyncAllProducts::class, 1);
        Queue::assertPushed(SyncSingleProduct::class, 1);
    }

    #[Test]
    public function test_options_command_queues_job_for_correct_entity_type(): void
    {
        Queue::fake();

        $categoryType = EntityType::factory()->create(['name' => 'category']);

        $this->artisan('sync:magento:options', ['entityType' => 'category'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncAttributeOptions::class, function ($job) use ($categoryType) {
            return $job->entityType->id === $categoryType->id;
        });
    }

    #[Test]
    public function test_sync_command_queues_job_for_correct_entity_type(): void
    {
        Queue::fake();

        $brandType = EntityType::factory()->create(['name' => 'brand']);

        $this->artisan('sync:magento', ['entityType' => 'brand'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncAllProducts::class, function ($job) use ($brandType) {
            return $job->entityType->id === $brandType->id;
        });
    }

    #[Test]
    public function test_cleanup_command_deletes_old_sync_results(): void
    {
        // Create old sync run with results (35 days ago)
        $oldSyncRun = \App\Models\SyncRun::factory()->create([
            'started_at' => now()->subDays(35),
        ]);
        $oldResult = \App\Models\SyncResult::factory()->create([
            'sync_run_id' => $oldSyncRun->id,
            'created_at' => now()->subDays(35),
        ]);

        // Create recent sync run with results (10 days ago)
        $recentSyncRun = \App\Models\SyncRun::factory()->create([
            'started_at' => now()->subDays(10),
        ]);
        $recentResult = \App\Models\SyncResult::factory()->create([
            'sync_run_id' => $recentSyncRun->id,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('sync:cleanup')
            ->expectsOutput('Cleaned up 1 sync results older than 30 days.')
            ->assertExitCode(0);

        // Old result should be deleted
        $this->assertDatabaseMissing('sync_results', [
            'id' => $oldResult->id,
        ]);

        // Recent result should still exist
        $this->assertDatabaseHas('sync_results', [
            'id' => $recentResult->id,
        ]);
    }

    #[Test]
    public function test_cleanup_command_respects_custom_days_option(): void
    {
        // Create sync result 15 days ago
        $syncRun = \App\Models\SyncRun::factory()->create([
            'started_at' => now()->subDays(15),
        ]);
        $result = \App\Models\SyncResult::factory()->create([
            'sync_run_id' => $syncRun->id,
            'created_at' => now()->subDays(15),
        ]);

        $this->artisan('sync:cleanup', ['--days' => 10])
            ->expectsOutputToContain('10 days')
            ->assertExitCode(0);

        // Result should be deleted since it's older than 10 days
        $this->assertDatabaseMissing('sync_results', [
            'id' => $result->id,
        ]);
    }
}

