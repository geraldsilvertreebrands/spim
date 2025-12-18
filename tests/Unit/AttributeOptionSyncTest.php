<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\SyncRun;
use App\Services\MagentoApiClient;
use App\Services\Sync\AttributeOptionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttributeOptionSyncTest extends TestCase
{
    use RefreshDatabase;

    private EntityType $entityType;

    private MagentoApiClient $magentoClient;

    private SyncRun $syncRun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityType = EntityType::where('name', 'product')->firstOrFail();
        $this->syncRun = SyncRun::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'sync_type' => 'options',
            'triggered_by' => 'schedule',
            'user_id' => null, // No user for schedule
        ]);
        $this->magentoClient = Mockery::mock(MagentoApiClient::class);
    }

    #[Test]
    public function test_syncs_options_from_magento_to_spim(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red', 'BLU' => 'Blue'],
        ]);

        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('color')
            ->once()
            ->andReturn([
                ['label' => 'Red', 'value' => 'RED'],
                ['label' => 'Blue', 'value' => 'BLU'],
                ['label' => 'Green', 'value' => 'GRN'],
            ]);

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $result = $sync->sync();

        // Attribute should now have all three options from Magento
        $attribute->refresh();
        $this->assertArrayHasKey('RED', $attribute->allowed_values);
        $this->assertArrayHasKey('BLU', $attribute->allowed_values);
        $this->assertArrayHasKey('GRN', $attribute->allowed_values);
        $this->assertEquals('Green', $attribute->allowed_values['GRN']);

        $this->assertEquals(1, $result['stats']['updated']);
    }

    #[Test]
    public function test_replaces_spim_options_when_magento_differs(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'size',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'],
        ]);

        // Magento has different options (source of truth)
        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('size')
            ->once()
            ->andReturn([
                ['label' => 'Small', 'value' => 'S'],
                ['label' => 'Medium', 'value' => 'M'],
            ]);

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $sync->sync();

        // SPIM should now have only the options from Magento
        $attribute->refresh();
        $this->assertCount(2, $attribute->allowed_values);
        $this->assertArrayHasKey('S', $attribute->allowed_values);
        $this->assertArrayHasKey('M', $attribute->allowed_values);
        $this->assertArrayNotHasKey('L', $attribute->allowed_values);
        $this->assertArrayNotHasKey('XL', $attribute->allowed_values);
    }

    #[Test]
    public function test_skips_attributes_that_are_already_synced(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red', 'BLU' => 'Blue'],
        ]);

        // Magento has exact same options
        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('color')
            ->once()
            ->andReturn([
                ['label' => 'Red', 'value' => 'RED'],
                ['label' => 'Blue', 'value' => 'BLU'],
            ]);

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $result = $sync->sync();

        // Should be skipped since already in sync
        $this->assertEquals(1, $result['stats']['skipped']);
        $this->assertEquals(0, $result['stats']['updated']);
    }

    #[Test]
    public function test_only_syncs_select_and_multiselect_attributes(): void
    {
        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'to_external',
        ]);

        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'is_sync' => 'to_external',
        ]);

        $this->magentoClient->shouldNotReceive('getAttributeOptions');

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $result = $sync->sync();

        // No attributes should be processed
        $this->assertEquals(0, $result['stats']['created'] + $result['stats']['updated'] + $result['stats']['skipped'] + $result['stats']['errors']);
    }

    #[Test]
    public function test_only_syncs_attributes_with_is_sync_enabled(): void
    {
        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'internal_color',
            'data_type' => 'select',
            'is_sync' => 'no',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        $this->magentoClient->shouldNotReceive('getAttributeOptions');

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $result = $sync->sync();

        $this->assertEquals(0, $result['stats']['created'] + $result['stats']['updated'] + $result['stats']['skipped'] + $result['stats']['errors']);
    }

    #[Test]
    public function test_logs_sync_results_to_database(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('color')
            ->once()
            ->andReturn([
                ['label' => 'Red', 'value' => 'RED'],
                ['label' => 'Blue', 'value' => 'BLU'],
            ]);

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $sync->sync();

        // Should have created a sync result
        $this->assertDatabaseHas('sync_results', [
            'sync_run_id' => $this->syncRun->id,
            'attribute_id' => $attribute->id,
            'item_identifier' => 'color',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function test_handles_magento_api_errors(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('color')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $result = $sync->sync();

        // Should track the error
        $this->assertEquals(1, $result['stats']['errors']);

        // Should log error to database
        $this->assertDatabaseHas('sync_results', [
            'sync_run_id' => $this->syncRun->id,
            'attribute_id' => $attribute->id,
            'status' => 'error',
        ]);
    }

    #[Test]
    public function test_updates_stats_correctly(): void
    {
        // Create multiple attributes
        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'size',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['S' => 'Small'],
        ]);

        // Mock responses
        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('color')
            ->once()
            ->andReturn([
                ['label' => 'Red', 'value' => 'RED'],
                ['label' => 'Blue', 'value' => 'BLU'],
            ]);

        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('size')
            ->once()
            ->andReturn([
                ['label' => 'Small', 'value' => 'S'],
            ]);

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $result = $sync->sync();

        // Verify stats
        $totalProcessed = $result['stats']['created'] + $result['stats']['updated'] + $result['stats']['skipped'] + $result['stats']['errors'];
        $this->assertEquals(2, $totalProcessed);
        $this->assertEquals(1, $result['stats']['updated']); // color updated
        $this->assertEquals(1, $result['stats']['skipped']); // size already in sync
        $this->assertEquals(0, $result['stats']['errors']);
    }

    #[Test]
    public function test_sync_run_is_updated_with_final_stats(): void
    {
        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        $this->magentoClient->shouldReceive('getAttributeOptions')
            ->with('color')
            ->once()
            ->andReturn([
                ['label' => 'Red', 'value' => 'RED'],
            ]);

        // Use SyncRunService to properly update sync run
        $service = new \App\Services\Sync\SyncRunService;
        $syncRun = $service->run(
            'options',
            $this->entityType,
            null,
            'schedule',
            function ($run) {
                $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $run);
                $result = $sync->sync();

                return $result['stats'] ?? [];
            }
        );

        // Sync run should be updated by SyncRunService
        $this->assertEquals('completed', $syncRun->status);
        $this->assertEquals(1, $syncRun->total_items);
        $this->assertEquals(0, $syncRun->failed_items);
        $this->assertNotNull($syncRun->completed_at);
    }

    #[Test]
    public function test_handles_no_synced_attributes_gracefully(): void
    {
        // Create attributes that are not marked for sync
        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'internal_color',
            'data_type' => 'select',
            'is_sync' => 'no', // Not synced
            'allowed_values' => ['RED' => 'Red'],
        ]);

        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text', // Not select/multiselect
            'is_sync' => 'to_external',
        ]);

        // Should not call Magento API at all
        $this->magentoClient->shouldNotReceive('getAttributeOptions');

        $sync = new AttributeOptionSync($this->magentoClient, $this->entityType, $this->syncRun);
        $result = $sync->sync();

        // Should complete successfully with no operations
        $this->assertEquals(0, $result['stats']['created']);
        $this->assertEquals(0, $result['stats']['updated']);
        $this->assertEquals(0, $result['stats']['skipped']);
        $this->assertEquals(0, $result['stats']['errors']);

        // Note: The sync run status is updated by the job, not the service
        // This test only verifies the service behavior
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
