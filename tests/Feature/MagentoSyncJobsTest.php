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

        $job = new SyncSingleProduct($entity, null, $this->user->id, 'user');
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

        $job = new SyncSingleProduct($entity, null, $this->user->id, 'user');
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

        $job = new SyncSingleProduct($entity, null, $this->user->id, 'user');
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

        $job = new SyncSingleProduct($entity, null, $this->user->id, 'user');
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

        SyncSingleProduct::dispatch($entity, null, $this->user->id, 'user');

        Queue::assertPushed(SyncSingleProduct::class, function ($job) use ($entity) {
            return $job->entityOrType->id === $entity->id &&
                   $job->entityId === null &&
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

    #[Test]
    public function test_sync_imports_select_attribute_as_plain_string(): void
    {
        // Create a select attribute
        $colorAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'from_external',
            'editable' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => ['16802' => 'Red', '16803' => 'Blue'],
        ]);

        // Mock Magento product with select attribute
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [['sku' => 'TEST-SELECT']],
            ], 200),
            'magento.test/rest/V1/products/TEST-SELECT' => Http::response([
                'sku' => 'TEST-SELECT',
                'name' => 'Test Product',
                'custom_attributes' => [
                    ['attribute_code' => 'color', 'value' => '16802'], // Magento returns as string
                ],
            ], 200),
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'select',
                'backend_type' => 'int',
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        // Verify the entity was created
        $entity = Entity::where('entity_id', 'TEST-SELECT')->first();
        $this->assertNotNull($entity);

        // Verify select value is stored as plain string
        $record = \DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $colorAttr->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('16802', $record->value_current);
        $this->assertEquals('16802', $record->value_approved);
        $this->assertEquals('16802', $record->value_live);
    }

    #[Test]
    public function test_sync_imports_multiselect_attribute_as_json_array(): void
    {
        // Create a multiselect attribute
        $categoryAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'category_ids',
            'data_type' => 'multiselect',
            'is_sync' => 'from_external',
            'editable' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => ['16802' => 'Cat1', '16722' => 'Cat2', '16823' => 'Cat3'],
        ]);

        // Mock Magento product with multiselect attribute (comma-separated)
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [['sku' => 'TEST-MULTI']],
            ], 200),
            'magento.test/rest/V1/products/TEST-MULTI' => Http::response([
                'sku' => 'TEST-MULTI',
                'name' => 'Test Product',
                'custom_attributes' => [
                    ['attribute_code' => 'category_ids', 'value' => '16802,16722'], // Comma-separated
                ],
            ], 200),
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'multiselect',
                'backend_type' => 'varchar',
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        // Verify the entity was created
        $entity = Entity::where('entity_id', 'TEST-MULTI')->first();
        $this->assertNotNull($entity);

        // Verify multiselect value is stored as JSON array
        $record = \DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $categoryAttr->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('["16802","16722"]', $record->value_current);
        $this->assertEquals('["16802","16722"]', $record->value_approved);
        $this->assertEquals('["16802","16722"]', $record->value_live);

        // Verify it decodes correctly
        $decoded = json_decode($record->value_current, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(['16802', '16722'], $decoded);
    }

    #[Test]
    public function test_sync_exports_select_attribute_as_string(): void
    {
        // Create entity with select attribute
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-EXPORT-SELECT',
        ]);

        $colorAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'editable' => 'yes',
            'needs_approval' => 'no',
            'allowed_values' => ['16802' => 'Red'],
        ]);

        // Set the value in SPIM
        \DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $colorAttr->id,
            'value_current' => '16802',
            'value_approved' => '16802',
            'value_live' => null, // Not yet synced
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock Magento API
        Http::fake([
            'magento.test/rest/V1/products/TEST-EXPORT-SELECT' => Http::response([
                'sku' => 'TEST-EXPORT-SELECT',
                'name' => 'Test',
            ], 200),
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'select',
            ], 200),
        ]);

        $job = new SyncSingleProduct($entity, null, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        // Verify the PUT request was made with correct format
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') {
                return false;
            }
            $data = $request->data();
            $customAttrs = $data['product']['custom_attributes'] ?? [];
            
            foreach ($customAttrs as $attr) {
                if ($attr['attribute_code'] === 'color') {
                    // Should be a plain string, not JSON array
                    return $attr['value'] === '16802';
                }
            }
            return false;
        });
    }

    #[Test]
    public function test_sync_exports_multiselect_attribute_as_comma_separated(): void
    {
        // Create entity with multiselect attribute
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-EXPORT-MULTI',
        ]);

        $categoryAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'category_ids',
            'data_type' => 'multiselect',
            'is_sync' => 'to_external',
            'editable' => 'yes',
            'needs_approval' => 'no',
            'allowed_values' => ['16802' => 'Cat1', '16722' => 'Cat2'],
        ]);

        // Set the value in SPIM as JSON array
        \DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $categoryAttr->id,
            'value_current' => '["16802","16722"]', // Stored as JSON
            'value_approved' => '["16802","16722"]',
            'value_live' => null, // Not yet synced
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock Magento API
        Http::fake([
            'magento.test/rest/V1/products/TEST-EXPORT-MULTI' => Http::response([
                'sku' => 'TEST-EXPORT-MULTI',
                'name' => 'Test',
            ], 200),
            'magento.test/rest/V1/products/attributes/*' => Http::response([
                'frontend_input' => 'multiselect',
            ], 200),
        ]);

        $job = new SyncSingleProduct($entity, null, $this->user->id, 'user');
        $job->handle(app(\App\Services\MagentoApiClient::class), app(\App\Services\EavWriter::class));

        // Verify the PUT request was made with comma-separated format
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') {
                return false;
            }
            $data = $request->data();
            $customAttrs = $data['product']['custom_attributes'] ?? [];
            
            foreach ($customAttrs as $attr) {
                if ($attr['attribute_code'] === 'category_ids') {
                    // Should be comma-separated string, not JSON array
                    return $attr['value'] === '16802,16722';
                }
            }
            return false;
        });
    }
}

