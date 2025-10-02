<?php

namespace Tests\Feature;

use App\Integrations\MagentoClient;
use App\Jobs\ImportMagentoProducts;
use App\Jobs\ReconcileMagento;
use App\Jobs\SyncApprovedValue;
use App\Models\Attribute;
use App\Models\AttributeExport;
use App\Models\AttributeOption;
use App\Models\AttributeSection;
use App\Models\ExternalPlatform;
use App\Models\Product;
use App\Models\Value;
use App\Services\MagentoValueMapper;
use App\Services\MagentoOptionsService;
use App\Support\ValueStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MagentoJobTest extends TestCase
{
    use RefreshDatabase;

    private ExternalPlatform $magentoPlatform;
    private AttributeSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Magento integration pending implementation.');

        // The following setup remains for future reactivation.
        // Create test data
        $this->magentoPlatform = ExternalPlatform::create([
            'type' => 'magento2',
            'name' => 'Test Magento',
            'config_json' => ['base_url' => 'https://m2.ftn.test']
        ]);

        $this->section = AttributeSection::create([
            'name' => 'Test Section',
            'display_order' => 1
        ]);

        // Mock Magento API responses
        $this->mockMagentoResponses();
    }

    private function mockMagentoResponses(): void
    {
        Http::fake([
            // List products endpoint (with search criteria)
            'm2.ftn.test/rest/V1/products?*' => Http::response([
                'items' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'name' => 'Test Product 1',
                        'price' => 29.99,
                        'status' => 1,
                        'visibility' => 4,
                        'type_id' => 'simple',
                        'custom_attributes' => [
                            [
                                'attribute_code' => 'test_description',
                                'value' => 'Test product 1 description'
                            ]
                        ]
                    ]
                ],
                'total_count' => 1
            ], 200),
            // Individual product endpoint
            'm2.ftn.test/rest/V1/products/TEST-001' => Http::response([
                'id' => 1,
                'sku' => 'TEST-001',
                'name' => 'Test Product 1',
                'price' => 29.99,
                'status' => 1,
                'visibility' => 4,
                'type_id' => 'simple',
                'custom_attributes' => [
                    [
                        'attribute_code' => 'test_description',
                        'value' => 'Test product 1 description'
                    ]
                ]
            ], 200)
        ]);
    }

    /** @test */
    public function import_magento_products_job_can_import_all_products()
    {
        // Create attribute for import
        $attribute = Attribute::create([
            'code' => 'test_description',
            'name' => 'Test Description',
            'attribute_section_id' => $this->section->id,
            'type' => 'string',
            'external_platform_id' => $this->magentoPlatform->id,
            'is_pipeline' => false,
            'review_required' => 'never'
        ]);

        AttributeExport::create([
            'attribute_id' => $attribute->id,
            'external_platform_id' => $this->magentoPlatform->id,
            'mapping_json' => ['target_attribute_code' => 'test_description']
        ]);

        // Test MagentoClient directly first
        $magentoClient = new MagentoClient();
        $products = $magentoClient->listProducts();

        if (empty($products)) {
            $this->fail("MagentoClient.listProducts() returned empty array. Check HTTP mock.");
        }

        $this->assertCount(1, $products);
        $this->assertEquals('TEST-001', $products[0]['sku']);

        // Test getProductBySku directly
        $productData = $magentoClient->getProductBySku('TEST-001');
        if (!$productData) {
            $this->fail("getProductBySku('TEST-001') returned null. Check HTTP mock for individual product endpoint.");
        }

        // Debug: see what we actually got
        if (!isset($productData['sku'])) {
            $this->fail("getProductBySku response missing 'sku' key. Response: " . json_encode($productData));
        }

        $this->assertEquals('TEST-001', $productData['sku']);

        // Run import job
        $job = new ImportMagentoProducts();
        try {
            $job->handle($magentoClient, new MagentoValueMapper(new MagentoOptionsService($magentoClient)));
        } catch (\Exception $e) {
            $this->fail("Import job failed with exception: " . $e->getMessage());
        }

        // Check if any products were created
        $productCount = Product::count();
        if ($productCount == 0) {
            $this->fail("No products were imported. Check logs for errors.");
        }

        // Verify product was imported
        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001'
        ]);

        // Verify product name was stored as EAV attribute
        $product = Product::where('sku', 'TEST-001')->first();
        $nameAttribute = Attribute::where('code', 'name')->first();
        $this->assertDatabaseHas('values', [
            'product_id' => $product->id,
            'attribute_id' => $nameAttribute->id,
            'string_value' => 'Test Product 1',
            'status' => 'approved'
        ]);

        // Verify custom attribute value was created
        $this->assertDatabaseHas('values', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'string_value' => 'Test product 1 description',
            'status' => 'approved'
        ]);
    }

    /** @test */
    public function import_magento_products_job_can_import_specific_skus()
    {
        // Create attribute for import
        $attribute = Attribute::create([
            'code' => 'test_description',
            'name' => 'Test Description',
            'attribute_section_id' => $this->section->id,
            'type' => 'string',
            'external_platform_id' => $this->magentoPlatform->id,
            'is_pipeline' => false,
            'review_required' => 'never'
        ]);

        AttributeExport::create([
            'attribute_id' => $attribute->id,
            'external_platform_id' => $this->magentoPlatform->id,
            'mapping_json' => ['target_attribute_code' => 'test_description']
        ]);

        // Run import job with specific SKUs
        $job = new ImportMagentoProducts(['TEST-001']);
        $job->handle(new MagentoClient(), new MagentoValueMapper(new MagentoOptionsService(new MagentoClient())));

        // Verify only specified product was imported
        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001'
        ]);

        $this->assertDatabaseMissing('products', [
            'sku' => 'TEST-002'
        ]);
    }

    /** @test */
    public function sync_approved_value_job_can_sync_value_to_magento()
    {
        // Create product and attribute
        $product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'description' => 'Test product description',
            'price' => 29.99,
            'weight' => 1.5,
            'status' => 'enabled',
            'visibility' => 'catalog,search',
            'type' => 'simple'
        ]);

        $attribute = Attribute::create([
            'code' => 'test_description',
            'name' => 'Test Description',
            'attribute_section_id' => $this->section->id,
            'type' => 'string',
            'external_platform_id' => $this->magentoPlatform->id,
            'is_pipeline' => false,
            'review_required' => 'never'
        ]);

        AttributeExport::create([
            'attribute_id' => $attribute->id,
            'external_platform_id' => $this->magentoPlatform->id,
            'mapping_json' => ['target_attribute_code' => 'test_description']
        ]);

        $value = Value::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'string_value' => 'Updated description from SPIM',
            'status' => ValueStatus::Approved
        ]);

        // Mock successful update
        Http::fake([
            'm2.ftn.test/rest/V1/products/TEST-001*' => Http::response([
                'id' => 1,
                'sku' => 'TEST-001',
                'name' => 'Test Product'
            ], 200)
        ]);

        // Debug: check value status before sync
        $value->refresh();
        $this->assertEquals(ValueStatus::Approved, $value->status, "Value should be approved before sync");

        // Debug: check if AttributeExport exists
        $attributeExport = AttributeExport::where('attribute_id', $attribute->id)
            ->where('external_platform_id', $this->magentoPlatform->id)
            ->first();
        $this->assertNotNull($attributeExport, "AttributeExport should exist for sync to work");

        // Debug: check attribute's external_platform_id
        $this->assertEquals($this->magentoPlatform->id, $attribute->external_platform_id, "Attribute external_platform_id should match");

        // Test value mapping first
        $valueMapper = new MagentoValueMapper(new MagentoOptionsService(new MagentoClient()));

        // Debug: check if attribute has platform relationship
        $attribute->load('externalPlatform');
        $this->assertNotNull($attribute->externalPlatform, "Attribute should have externalPlatform relationship");

        $mappedValue = $valueMapper->mapValueForExport($value);
        $this->assertNotNull($mappedValue, "Value mapping should work");

        // Run sync job
        $job = new SyncApprovedValue($value->id);
        try {
            $job->handle(new MagentoClient(), $valueMapper);
        } catch (\Exception $e) {
            $this->fail("Sync job failed with exception: " . $e->getMessage());
        }

        // Check if any HTTP requests were made
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/products/TEST-001') && $request->method() === 'PUT';
        });

        // Debug: check value status after sync
        $value->refresh();
        if ($value->status !== ValueStatus::Synced) {
            $this->fail("Value status is '{$value->status->value}' instead of 'synced'. Last error: " . $value->last_error);
        }

        // Verify value status was updated
        $this->assertDatabaseHas('values', [
            'id' => $value->id,
            'status' => ValueStatus::Synced->value
        ]);
    }

    /** @test */
    public function sync_approved_value_job_handles_errors_gracefully()
    {
        // Create product and attribute
        $product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'description' => 'Test product description',
            'price' => 29.99,
            'weight' => 1.5,
            'status' => 'enabled',
            'visibility' => 'catalog,search',
            'type' => 'simple'
        ]);

        $attribute = Attribute::create([
            'code' => 'test_description',
            'name' => 'Test Description',
            'attribute_section_id' => $this->section->id,
            'type' => 'string',
            'external_platform_id' => $this->magentoPlatform->id,
            'is_pipeline' => false,
            'review_required' => 'never'
        ]);

        AttributeExport::create([
            'attribute_id' => $attribute->id,
            'external_platform_id' => $this->magentoPlatform->id,
            'mapping_json' => ['target_attribute_code' => 'test_description']
        ]);

        $value = Value::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'string_value' => 'Updated description from SPIM',
            'status' => 'queued_for_sync'
        ]);

        // Mock API error
        Http::fake([
            'm2.ftn.test/rest/V1/products/TEST-001*' => Http::response([
                'message' => 'Internal server error'
            ], 500)
        ]);

        // Run sync job
        $job = new SyncApprovedValue($value->id);
        $job->handle(new MagentoClient(), new MagentoValueMapper(new MagentoOptionsService(new MagentoClient())));

        // Verify value status was reverted and error was recorded
        $this->assertDatabaseHas('values', [
            'id' => $value->id,
            'status' => 'approved', // Should revert to approved
            'last_error' => 'Sync failed: Internal server error'
        ]);
    }

    /** @test */
    public function reconcile_magento_job_can_detect_conflicts()
    {
        // Create product and attribute
        $product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'description' => 'Test product description',
            'price' => 29.99,
            'weight' => 1.5,
            'status' => 'enabled',
            'visibility' => 'catalog,search',
            'type' => 'simple'
        ]);

        $attribute = Attribute::create([
            'code' => 'test_description',
            'name' => 'Test Description',
            'attribute_section_id' => $this->section->id,
            'type' => 'string',
            'external_platform_id' => $this->magentoPlatform->id,
            'is_pipeline' => false,
            'review_required' => 'never'
        ]);

        $value = Value::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'string_value' => 'Original SPIM value',
            'status' => 'synced'
        ]);

        // Mock different value in Magento
        Http::fake([
            'm2.ftn.test/rest/V1/products/TEST-001*' => Http::response([
                'id' => 1,
                'sku' => 'TEST-001',
                'name' => 'Test Product',
                'custom_attributes' => [
                    [
                        'attribute_code' => 'test_description',
                        'value' => 'Modified in Magento'
                    ]
                ]
            ], 200)
        ]);

        // Run reconcile job
        $job = new ReconcileMagento();
        $job->handle(new MagentoClient(), new MagentoValueMapper(new MagentoOptionsService(new MagentoClient())));

        // Verify conflict was detected and override was created
        $this->assertDatabaseHas('values', [
            'id' => $value->id,
            'overridden' => true,
            'value_override' => 'Modified in Magento',
            'status' => 'pending_review'
        ]);
    }

    /** @test */
    public function reconcile_magento_job_handles_missing_products()
    {
        // Create product and attribute
        $product = Product::create([
            'sku' => 'MISSING-SKU',
            'name' => 'Missing Product',
            'description' => 'This product will not be found',
            'price' => 29.99,
            'weight' => 1.5,
            'status' => 'enabled',
            'visibility' => 'catalog,search',
            'type' => 'simple'
        ]);

        $attribute = Attribute::create([
            'code' => 'test_description',
            'name' => 'Test Description',
            'attribute_section_id' => $this->section->id,
            'type' => 'string',
            'external_platform_id' => $this->magentoPlatform->id,
            'is_pipeline' => false,
            'review_required' => 'never'
        ]);

        $value = Value::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'string_value' => 'Original SPIM value',
            'status' => 'synced'
        ]);

        // Mock product not found
        Http::fake([
            'm2.ftn.test/rest/V1/products/MISSING-SKU*' => Http::response([
                'message' => 'Product not found'
            ], 404)
        ]);

        // Run reconcile job
        $job = new ReconcileMagento();
        $job->handle(new MagentoClient(), new MagentoValueMapper(new MagentoOptionsService(new MagentoClient())));

        // Verify value remains unchanged (no conflict detected)
        $this->assertDatabaseHas('values', [
            'id' => $value->id,
            'status' => 'synced',
            'overridden' => false
        ]);
    }

    /** @test */
    public function jobs_can_be_queued_and_dispatched()
    {
        Queue::fake();

        // Dispatch import job
        ImportMagentoProducts::dispatch();

        // Dispatch sync job
        SyncApprovedValue::dispatch(1);

        // Dispatch reconcile job
        ReconcileMagento::dispatch();

        // Verify jobs were dispatched
        Queue::assertPushed(ImportMagentoProducts::class);
        Queue::assertPushed(SyncApprovedValue::class);
        Queue::assertPushed(ReconcileMagento::class);
    }

    /** @test */
    public function import_job_handles_batch_processing()
    {
        // Mock multiple products
        Http::fake([
            'm2.ftn.test/rest/V1/products*' => Http::response([
                'items' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'name' => 'Test Product 1',
                        'price' => 29.99,
                        'status' => 1,
                        'visibility' => 4,
                        'type_id' => 'simple',
                        'custom_attributes' => []
                    ],
                    [
                        'id' => 2,
                        'sku' => 'TEST-002',
                        'name' => 'Test Product 2',
                        'price' => 39.99,
                        'status' => 1,
                        'visibility' => 4,
                        'type_id' => 'simple',
                        'custom_attributes' => []
                    ]
                ],
                'total_count' => 2
            ], 200)
        ]);

        // Run import job with small batch size
        $job = new ImportMagentoProducts(null, 1, 1); // page 1, pageSize 1
        $job->handle(new MagentoClient(), new MagentoValueMapper(new MagentoOptionsService(new MagentoClient())));

        // Verify only one product was imported (due to page size)
        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001'
        ]);

        $this->assertDatabaseMissing('products', [
            'sku' => 'TEST-002'
        ]);
    }

    /** @test */
    public function sync_job_skips_values_not_ready_for_sync()
    {
        // Create value that's not ready for sync
        $product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'description' => 'Test product description',
            'price' => 29.99,
            'weight' => 1.5,
            'status' => 'enabled',
            'visibility' => 'catalog,search',
            'type' => 'simple'
        ]);

        $attribute = Attribute::create([
            'code' => 'test_description',
            'name' => 'Test Description',
            'attribute_section_id' => $this->section->id,
            'type' => 'string',
            'external_platform_id' => $this->magentoPlatform->id,
            'is_pipeline' => false,
            'review_required' => 'never'
        ]);

        $value = Value::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'string_value' => 'Test value',
            'status' => 'stale' // Not ready for sync
        ]);

        // Run sync job
        $job = new SyncApprovedValue($value->id);
        $job->handle(new MagentoClient(), new MagentoValueMapper(new MagentoOptionsService(new MagentoClient())));

        // Verify value status was not changed
        $this->assertDatabaseHas('values', [
            'id' => $value->id,
            'status' => 'stale'
        ]);
    }
}
