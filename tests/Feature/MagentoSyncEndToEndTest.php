<?php

namespace Tests\Feature;

use App\Jobs\Sync\SyncAllProducts;
use App\Jobs\Sync\SyncAttributeOptions;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\SyncRun;
use App\Services\EavWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagentoSyncEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private EntityType $entityType;
    private EavWriter $eavWriter;

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
        $this->eavWriter = app(EavWriter::class);
    }

    #[Test]
    public function test_full_sync_workflow_with_new_product(): void
    {
        // Step 1: Create synced attributes
        $colorAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
            'is_sync' => 'to_external',
            'allowed_values' => ['RED' => 'Red'],
        ]);

        $nameAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'name',
            'data_type' => 'text',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        // Step 2: Mock Magento API for options sync
        Http::fake([
            'magento.test/rest/V1/products/attributes/color/options' => Http::response([
                ['label' => 'Red', 'value' => 'RED'],
                ['label' => 'Blue', 'value' => 'BLU'],
            ], 200),
        ]);

        // Step 3: Run option sync
        $optionsJob = new SyncAttributeOptions($this->entityType, null, 'schedule');
        $optionsJob->handle(app(\App\Services\MagentoApiClient::class));

        // Verify options were synced
        $colorAttr->refresh();
        $this->assertArrayHasKey('BLU', $colorAttr->allowed_values);

        // Step 4: Mock Magento API for product import
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [
                    [
                        'sku' => 'NEW-PROD-001',
                        'name' => 'New Product',
                        'custom_attributes' => [],
                    ],
                ],
            ], 200),
            'magento.test/rest/V1/products/NEW-PROD-001' => Http::response([
                'sku' => 'NEW-PROD-001',
                'name' => 'New Product',
                'custom_attributes' => [],
            ], 200),
        ]);

        // Step 5: Run product sync
        $productsJob = new SyncAllProducts($this->entityType, null, 'schedule');
        $productsJob->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        // Step 6: Verify entity was created
        $this->assertDatabaseHas('entities', [
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'NEW-PROD-001',
        ]);

        // Step 7: Verify attribute value was imported
        $entity = Entity::where('entity_id', 'NEW-PROD-001')->first();
        $this->assertDatabaseHas('eav_input', [
            'entity_id' => $entity->id,
            'attribute_id' => $nameAttr->id,
            'value_current' => 'New Product',
        ]);

        // Step 8: Verify sync runs were logged
        $this->assertEquals(2, SyncRun::count()); // One for options, one for products
    }

    #[Test]
    public function test_full_sync_workflow_with_existing_product(): void
    {
        // Create existing entity
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'EXISTING-001',
        ]);

        // Create attributes
        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'yes',
        ]);

        // Set SPIM value that needs to be pushed
        DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $descAttr->id,
            'value_current' => 'New desc',
            'value_approved' => 'New desc',
            'value_live' => 'Old desc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock Magento API
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [['sku' => 'EXISTING-001']],
            ], 200),
            'magento.test/rest/V1/products/EXISTING-001' => Http::response([
                'sku' => 'EXISTING-001',
                'custom_attributes' => [],
            ], 200, ['X-Request-Count' => '1']),
        ]);

        // Run sync
        $job = new SyncAllProducts($this->entityType, null, 'schedule');
        $job->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        // Verify value_live was updated
        $updated = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $descAttr->id)
            ->first();

        $this->assertEquals('New desc', $updated->value_live);

        // Verify HTTP request was made to update product
        Http::assertSent(function ($request) {
            return $request->method() === 'PUT' &&
                   str_contains($request->url(), '/products/EXISTING-001');
        });
    }

    #[Test]
    public function test_bidirectional_sync(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'BIDIRECTIONAL-001',
        ]);

        // Input attribute (pull from Magento)
        $priceAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        // Versioned attribute (push to Magento)
        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'yes',
        ]);

        // Set SPIM description
        DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $descAttr->id,
            'value_current' => 'SPIM desc',
            'value_approved' => 'SPIM desc',
            'value_live' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock Magento with different price
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [['sku' => 'BIDIRECTIONAL-001']],
            ], 200),
            'magento.test/rest/V1/products/BIDIRECTIONAL-001' => Http::response([
                'sku' => 'BIDIRECTIONAL-001',
                'price' => 49.99,
                'custom_attributes' => [],
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, null, 'schedule');
        $job->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        // Verify price was imported from Magento
        $this->assertDatabaseHas('eav_input', [
            'entity_id' => $entity->id,
            'attribute_id' => $priceAttr->id,
            'value_current' => '49.99',
        ]);

        // Verify description update was sent to Magento
        Http::assertSent(function ($request) {
            return $request->method() === 'PUT' &&
                   str_contains($request->url(), '/products/BIDIRECTIONAL-001');
        });
    }

    #[Test]
    public function test_sync_respects_attribute_type_rules(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        // Versioned: push to Magento
        $versionedAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'versioned_attr',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'yes',
        ]);

        // Input: pull from Magento
        $inputAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'input_attr',
            'data_type' => 'text',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        // Timeseries: not synced
        $timeseriesAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'timeseries_attr',
            'data_type' => 'text',
            'is_sync' => 'no',
            'editable' => 'yes',
        ]);

        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [['sku' => 'TEST-001']],
            ], 200),
            'magento.test/rest/V1/products/TEST-001' => Http::response([
                'sku' => 'TEST-001',
                'custom_attributes' => [
                    ['attribute_code' => 'input_attr', 'value' => 'From Magento'],
                    ['attribute_code' => 'timeseries_attr', 'value' => 'Should not sync'],
                ],
            ], 200),
        ]);

        $job = new SyncAllProducts($this->entityType, null, 'schedule');
        $job->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        // Input attr should be imported
        $this->assertDatabaseHas('eav_input', [
            'entity_id' => $entity->id,
            'attribute_id' => $inputAttr->id,
            'value_current' => 'From Magento',
        ]);

        // Timeseries attr should NOT be imported
        $timeseriesValue = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $timeseriesAttr->id)
            ->first();

        $this->assertNull($timeseriesValue);
    }

    #[Test]
    public function test_handles_magento_api_down(): void
    {
        Http::fake([
            'magento.test/*' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $job = new SyncAllProducts($this->entityType, null, 'schedule');
        $job->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        // Sync run should be marked as failed
        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();
        $this->assertNotNull($syncRun);
        $this->assertEquals('failed', $syncRun->status);
        $this->assertNotNull($syncRun->error_summary);
    }

    #[Test]
    public function test_partial_sync_with_some_failures(): void
    {
        Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'SUCCESS-001',
        ]);

        Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'FAIL-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products?*' => Http::response([
                'items' => [
                    ['sku' => 'SUCCESS-001'],
                    ['sku' => 'FAIL-001'],
                ],
            ], 200),
            'magento.test/rest/V1/products/SUCCESS-001' => Http::response([
                'sku' => 'SUCCESS-001',
            ], 200),
            'magento.test/rest/V1/products/FAIL-001' => Http::response([
                'message' => 'Product error',
            ], 500),
        ]);

        $job = new SyncAllProducts($this->entityType, null, 'schedule');
        $job->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();

        // Should have both successes and failures
        $this->assertGreaterThan(0, $syncRun->successful_items);
        $this->assertGreaterThan(0, $syncRun->failed_items);
        $this->assertEquals('failed', $syncRun->status);
    }

    #[Test]
    public function test_sync_run_records_created_with_correct_stats(): void
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

        $job = new SyncAllProducts($this->entityType, null, 'schedule');
        $job->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        $syncRun = SyncRun::where('entity_type_id', $this->entityType->id)->first();

        $this->assertNotNull($syncRun);
        $this->assertGreaterThan(0, $syncRun->total_items);
        $this->assertEquals(
            $syncRun->total_items,
            $syncRun->successful_items + $syncRun->failed_items + $syncRun->skipped_items
        );
        $this->assertNotNull($syncRun->completed_at);
    }

    #[Test]
    public function test_sync_results_contain_detailed_error_messages(): void
    {
        Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'ERROR-001',
        ]);

        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [['sku' => 'ERROR-001']],
            ], 200),
            'magento.test/rest/V1/products/ERROR-001' => Http::response([
                'message' => 'Detailed error: Invalid attribute value',
            ], 400),
        ]);

        $job = new SyncAllProducts($this->entityType, null, 'schedule');
        $job->handle(app(\App\Services\MagentoApiClient::class), $this->eavWriter);

        $this->assertDatabaseHas('sync_results', [
            'item_identifier' => 'ERROR-001',
            'status' => 'error',
        ]);

        $errorResult = DB::table('sync_results')
            ->where('item_identifier', 'ERROR-001')
            ->where('status', 'error')
            ->first();

        $this->assertNotNull($errorResult->error_message);
        $this->assertStringContainsString('error', strtolower($errorResult->error_message));
    }
}

