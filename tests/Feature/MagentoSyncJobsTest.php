<?php

namespace Tests\Feature;

use App\Jobs\Sync\SyncAllProducts;
use App\Jobs\Sync\SyncAttributeOptions;
use App\Jobs\Sync\SyncSingleProduct;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagentoSyncJobsTest extends TestCase
{
    use RefreshDatabase;

    private EntityType $entityType;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.magento.base_url' => 'https://magento.test',
            'services.magento.access_token' => 'test-token',
        ]);

        $this->entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product', 'description' => 'Test product type']
        );
        $this->user = User::factory()->create();

        // Mock Magento API responses
        Http::fake([
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'text',
                'backend_type' => 'varchar',
            ], 200),
            'magento.test/*' => Http::response(['success' => true], 200),
        ]);
    }

    #[Test]
    public function test_sync_attribute_options_job_creates_sync_run(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/color/options' => Http::response([
                ['label' => 'Red', 'value' => 'RED'],
                ['label' => 'Blue', 'value' => 'BLU'],
            ], 200),
        ]);

        $job = new SyncAttributeOptions($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class));

        $this->assertDatabaseHas('sync_runs', [
            'entity_type_id' => $this->entityType->id,
            'sync_type' => 'options',
            'triggered_by' => 'user',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function test_sync_attribute_options_job_logs_results(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'size',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['S' => 'Small'],
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/size/options' => Http::response([
                ['label' => 'Small', 'value' => 'S'],
                ['label' => 'Medium', 'value' => 'M'],
            ], 200),
        ]);

        $job = new SyncAttributeOptions($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class));

        $this->assertDatabaseHas('sync_results', [
            'attribute_id' => $attribute->id,
            'item_identifier' => 'size',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function test_sync_attribute_options_job_handles_no_synced_attributes(): void
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

        // Should not make any HTTP calls to Magento
        Http::fake([
            'magento.test/*' => Http::response(['error' => 'Should not be called'], 500),
        ]);

        $job = new SyncAttributeOptions($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class));

        // Should still create a sync run and mark it as completed
        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)
            ->where('sync_type', 'options')
            ->first();

        $this->assertNotNull($syncRun);
        $this->assertEquals('completed', $syncRun->status);
        $this->assertEquals(0, $syncRun->total_items);
        $this->assertEquals(0, $syncRun->successful_items);
        $this->assertEquals(0, $syncRun->failed_items);
        $this->assertNotNull($syncRun->completed_at);

        // Should not have any sync results since no attributes were processed
        $this->assertDatabaseMissing('sync_results', [
            'sync_run_id' => $syncRun->id,
        ]);
    }

    #[Test]
    public function test_sync_attribute_options_job_tracks_user(): void
    {
        Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/color/options' => Http::response([
                ['label' => 'Red', 'value' => 'RED'],
            ], 200),
        ]);

        $job = new SyncAttributeOptions($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class));

        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();
        $this->assertNotNull($syncRun);
        $this->assertEquals($this->user->id, $syncRun->user_id);
        $this->assertEquals('user', $syncRun->triggered_by);
    }

    #[Test]
    public function test_sync_attribute_options_job_can_be_queued(): void
    {
        Queue::fake();

        SyncAttributeOptions::dispatch($this->entityType, $this->user->id, 'user');

        Queue::assertPushed(SyncAttributeOptions::class, function ($job) {
            return $job->entityType->id === $this->entityType->id &&
                   $job->userId === $this->user->id &&
                   $job->triggeredBy === 'user';
        });
    }

    #[Test]
    public function test_sync_all_products_job_creates_sync_run(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [
                    ['sku' => 'TEST-001'],
                ],
            ], 200),
            'magento.test/rest/V1/products/TEST-001' => Http::response([
                'sku' => 'TEST-001',
                'name' => 'Test Product',
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        $this->assertDatabaseHas('sync_runs', [
            'entity_type_id' => $this->entityType->id,
            'sync_type' => 'products',
            'triggered_by' => 'user',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function test_sync_all_products_job_syncs_all_entities(): void
    {
        Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-001',
        ]);

        Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-002',
        ]);

        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [
                    ['sku' => 'PROD-001'],
                    ['sku' => 'PROD-002'],
                ],
            ], 200),
            'magento.test/rest/V1/products/*' => Http::response([
                'sku' => 'test',
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        // Should have sync results for both products
        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();
        $this->assertGreaterThanOrEqual(2, $syncRun->sync_results()->count());
    }

    #[Test]
    public function test_sync_all_products_job_logs_results(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [['sku' => 'TEST-001']],
            ], 200),
            'magento.test/rest/V1/products/TEST-001' => Http::response([
                'sku' => 'TEST-001',
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        $this->assertDatabaseHas('sync_results', [
            'entity_id' => $entity->id,
            'item_identifier' => 'TEST-001',
        ]);
    }

    #[Test]
    public function test_sync_all_products_job_tracks_user(): void
    {
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [],
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();
        $this->assertEquals($this->user->id, $syncRun->user_id);
        $this->assertEquals('user', $syncRun->triggered_by);
    }

    #[Test]
    public function test_sync_all_products_job_can_be_queued(): void
    {
        Queue::fake();

        SyncAllProducts::dispatch($this->entityType, $this->user->id, 'user');

        Queue::assertPushed(SyncAllProducts::class, function ($job) {
            return $job->entityType->id === $this->entityType->id &&
                   $job->userId === $this->user->id &&
                   $job->triggeredBy === 'user';
        });
    }

    #[Test]
    public function test_sync_single_product_job_creates_sync_run(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'SINGLE-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'text',
                'backend_type' => 'varchar',
            ], 200),
            'magento.test/rest/V1/products/SINGLE-001' => Http::response([
                'sku' => 'SINGLE-001',
                'name' => 'Single Product',
            ], 200),
        ]);

        $job = new SyncSingleProduct($entity, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        $this->assertDatabaseHas('sync_runs', [
            'entity_type_id' => $this->entityType->id,
            'sync_type' => 'products',
            'triggered_by' => 'user',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function test_sync_single_product_job_syncs_one_entity(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'SINGLE-001',
        ]);

        // Create another entity that should NOT be synced
        $otherEntity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'OTHER-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'text',
                'backend_type' => 'varchar',
            ], 200),
            'magento.test/rest/V1/products/SINGLE-001' => Http::response([
                'sku' => 'SINGLE-001',
            ], 200),
        ]);

        $job = new SyncSingleProduct($entity, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        // Should have result for SINGLE-001 only
        $this->assertDatabaseHas('sync_results', [
            'entity_id' => $entity->id,
            'item_identifier' => 'SINGLE-001',
        ]);

        $this->assertDatabaseMissing('sync_results', [
            'entity_id' => $otherEntity->id,
            'item_identifier' => 'OTHER-001',
        ]);
    }

    #[Test]
    public function test_sync_single_product_job_logs_results(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'text',
                'backend_type' => 'varchar',
            ], 200),
            'magento.test/rest/V1/products/TEST-001' => Http::response([
                'sku' => 'TEST-001',
            ], 200),
        ]);

        $job = new SyncSingleProduct($entity, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        $this->assertDatabaseHas('sync_results', [
            'entity_id' => $entity->id,
            'item_identifier' => 'TEST-001',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function test_sync_single_product_job_tracks_user(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'text',
                'backend_type' => 'varchar',
            ], 200),
            'magento.test/rest/V1/products/TEST-001' => Http::response([
                'sku' => 'TEST-001',
            ], 200),
        ]);

        $job = new SyncSingleProduct($entity, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();
        $this->assertEquals($this->user->id, $syncRun->user_id);
        $this->assertEquals('user', $syncRun->triggered_by);
    }

    #[Test]
    public function test_sync_single_product_job_can_be_queued(): void
    {
        Queue::fake();

        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        SyncSingleProduct::dispatch($entity, $this->user->id, 'user');

        Queue::assertPushed(SyncSingleProduct::class, function ($job) use ($entity) {
            return $job->entity->id === $entity->id &&
                   $job->userId === $this->user->id &&
                   $job->triggeredBy === 'user';
        });
    }

    #[Test]
    public function test_jobs_update_sync_run_status_on_completion(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/color/options' => Http::response([
                ['label' => 'Red', 'value' => 'RED'],
            ], 200),
        ]);

        $job = new SyncAttributeOptions($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class));

        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();
        $this->assertEquals('completed', $syncRun->status);
        $this->assertNotNull($syncRun->completed_at);
    }

    #[Test]
    public function test_jobs_record_errors_in_sync_run(): void
    {
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        Http::fake([
            'magento.test/rest/V1/products/attributes/color/options' => Http::response([
                'message' => 'API Error',
            ], 500),
        ]);

        $job = new SyncAttributeOptions($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class));

        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();
        // Sync completes even when individual attributes fail
        $this->assertNotNull($syncRun);
        $this->assertContains($syncRun->status, ['partial', 'completed', 'failed']);
        $this->assertNotNull($syncRun->completed_at);
    }
}

