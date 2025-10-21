<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\SyncRun;
use App\Services\EavWriter;
use App\Services\MagentoApiClient;
use App\Services\Sync\ProductSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductSyncTest extends TestCase
{
    use RefreshDatabase;

    private EntityType $entityType;
    private MagentoApiClient $magentoClient;
    private EavWriter $eavWriter;
    private SyncRun $syncRun;
    private string $skuNew;
    private string $skuExisting;
    private string $skuSpimOnly;
    private string $skuSingle;
    private string $skuTest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityType = EntityType::create([
            'name' => 'et_' . Str::lower(Str::random(8)),
            'display_name' => 'Test Type',
            'description' => 'Isolated test entity type',
        ]);
        $this->syncRun = SyncRun::factory()->forSchedule()->create([
            'entity_type_id' => $this->entityType->id,
            'sync_type' => 'products',
        ]);
        $this->magentoClient = Mockery::mock(MagentoApiClient::class);
        $this->eavWriter = app(EavWriter::class);

        // Generate unique identifiers to avoid cross-test collisions
        $this->skuNew = 'NEW-' . Str::upper(Str::random(8));
        $this->skuExisting = 'EXISTING-' . Str::upper(Str::random(8));
        $this->skuSpimOnly = 'SPIM-ONLY-' . Str::upper(Str::random(8));
        $this->skuSingle = 'SINGLE-' . Str::upper(Str::random(8));
        $this->skuTest = 'TEST-' . Str::upper(Str::random(8));
    }

    #[Test]
    public function test_imports_new_products_from_magento(): void
    {
        // Create synced input attribute
        $nameAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'name',
            'data_type' => 'text',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        // Mock getAttribute for validation
        $this->magentoClient->shouldReceive('getAttribute')
            ->with('name')
            ->once()
            ->andReturn(['frontend_input' => 'text', 'backend_type' => 'varchar']);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'sku' => $this->skuNew,
                        'name' => 'New Product',
                        'custom_attributes' => [],
                    ],
                ],
            ]);

        // Mock for push phase - check if product exists in Magento
        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuNew)
            ->once()
            ->andReturn(['sku' => 'NEW-001']); // Product exists in Magento

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $result = $sync->sync();

        // Entity should be created
        $this->assertDatabaseHas('entities', [
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuNew,
        ]);

        // Attribute value should be set in eav_versioned
        $entity = Entity::where('entity_id', $this->skuNew)->first();
        $this->assertDatabaseHas('eav_versioned', [
            'entity_id' => $entity->id,
            'attribute_id' => $nameAttr->id,
            'value_current' => 'New Product',
        ]);

        $this->assertEquals(1, $result['created']);
    }

    #[Test]
    public function test_updates_existing_products_with_input_attributes(): void
    {
        // Create existing entity
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuExisting,
        ]);

        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        // Set initial value using upsertVersioned
        $this->eavWriter->upsertVersioned($entity->id, $descAttr->id, 'Old description');

        // Mock getAttribute for validation
        $this->magentoClient->shouldReceive('getAttribute')
            ->with('description')
            ->once()
            ->andReturn(['frontend_input' => 'textarea', 'backend_type' => 'text']);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'sku' => $this->skuExisting,
                        'custom_attributes' => [
                            ['attribute_code' => 'description', 'value' => 'New description'],
                        ],
                    ],
                ],
            ]);

        // Mock for push phase - check if product exists in Magento
        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuExisting)
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        // Value should be updated in eav_versioned
        $this->assertDatabaseHas('eav_versioned', [
            'entity_id' => $entity->id,
            'attribute_id' => $descAttr->id,
            'value_current' => 'New description',
        ]);
    }

    #[Test]
    public function test_sets_all_three_value_fields_on_initial_import(): void
    {
        $priceAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        // Mock getAttribute for validation
        $this->magentoClient->shouldReceive('getAttribute')
            ->with('price')
            ->once()
            ->andReturn(['frontend_input' => 'price', 'backend_type' => 'decimal']);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'sku' => $this->skuNew,
                        'price' => 29.99,
                        'custom_attributes' => [],
                    ],
                ],
            ]);

        // Mock for push phase - check if product exists in Magento
        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuNew)
            ->once()
            ->andReturn(['sku' => 'NEW-001']); // Product exists in Magento

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        $entity = Entity::where('entity_id', $this->skuNew)->first();

        // All three value fields should be set identically on initial import
        $value = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $priceAttr->id)
            ->first();

        $this->assertEquals('29.99', $value->value_current);
        $this->assertEquals('29.99', $value->value_approved);
        $this->assertEquals('29.99', $value->value_live);
    }

    #[Test]
    public function test_creates_products_in_magento_when_missing(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuSpimOnly,
        ]);

        $nameAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'name',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'yes',
            'needs_approval' => 'no',
        ]);

        // Use upsertVersioned instead of writeVersioned
        $this->eavWriter->upsertVersioned($entity->id, $nameAttr->id, 'SPIM Product');

        // Mock getAttribute for validation
        $this->magentoClient->shouldReceive('getAttribute')
            ->with('name')
            ->once()
            ->andReturn(['frontend_input' => 'text', 'backend_type' => 'varchar']);

        // Mock Magento responses
        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => []]); // No products in Magento

        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuSpimOnly)
            ->once()
            ->andReturn(null); // Product not found (returns null)

        $this->magentoClient->shouldReceive('createProduct')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['sku'] === 'SPIM-ONLY-001' &&
                       $payload['status'] === 2; // disabled
            }))
            ->andReturn(['id' => 1, 'sku' => 'SPIM-ONLY-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $result = $sync->sync();

        $this->assertGreaterThanOrEqual(1, $result['created']);
    }

    #[Test]
    public function test_creates_products_as_disabled(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuNew,
        ]);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => []]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuNew)
            ->once()
            ->andReturn(null); // Product not found

        $this->magentoClient->shouldReceive('createProduct')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return isset($payload['status']) && $payload['status'] === 2;
            }))
            ->andReturn(['id' => 1, 'sku' => 'NEW-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $result = $sync->sync();

        // Assert that at least one product was created in Magento
        $this->assertGreaterThanOrEqual(1, $result['created']);
    }

    #[Test]
    public function test_updates_existing_products_with_versioned_attributes(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuExisting,
        ]);

        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'yes',
        ]);

        // Set approved value different from live value
        DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $descAttr->id,
            'value_current' => 'Draft desc',
            'value_approved' => 'Approved desc',
            'value_live' => 'Old live desc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock getAttribute for validation
        $this->magentoClient->shouldReceive('getAttribute')
            ->with('description')
            ->once()
            ->andReturn(['frontend_input' => 'textarea', 'backend_type' => 'text']);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuExisting)
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        $this->magentoClient->shouldReceive('updateProduct')
            ->once()
            ->with('EXISTING-001', Mockery::on(function ($payload) {
                return isset($payload['custom_attributes']) &&
                       collect($payload['custom_attributes'])->contains(fn ($attr) =>
                           $attr['attribute_code'] === 'description' && $attr['value'] === 'Approved desc'
                       );
            }))
            ->andReturn(['sku' => 'EXISTING-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        // value_live should be updated
        $updated = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $descAttr->id)
            ->first();

        $this->assertEquals('Approved desc', $updated->value_live);
    }

    #[Test]
    public function test_only_syncs_when_value_approved_differs_from_value_live(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuExisting,
        ]);

        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'yes',
        ]);

        // Set approved = live (already synced)
        DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $descAttr->id,
            'value_current' => 'Same desc',
            'value_approved' => 'Same desc',
            'value_live' => 'Same desc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock getAttribute for validation
        $this->magentoClient->shouldReceive('getAttribute')
            ->with('description')
            ->once()
            ->andReturn(['frontend_input' => 'textarea', 'backend_type' => 'text']);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuExisting)
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        // Should NOT call updateProduct since values are the same
        $this->magentoClient->shouldNotReceive('updateProduct');

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $result = $sync->sync();

        // Push phase should be skipped since approved == live
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
    }

    #[Test]
    public function test_uses_value_override_when_present(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuExisting,
        ]);

        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'overridable',
        ]);

        // Set override value
        DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $descAttr->id,
            'value_current' => 'Generated desc',
            'value_approved' => 'Manual override', // Override gets approved
            'value_override' => 'Manual override',
            'value_live' => 'Old desc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock getAttribute for validation
        $this->magentoClient->shouldReceive('getAttribute')
            ->with('description')
            ->once()
            ->andReturn(['frontend_input' => 'textarea', 'backend_type' => 'text']);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuExisting)
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        $this->magentoClient->shouldReceive('updateProduct')
            ->once()
            ->with('EXISTING-001', Mockery::on(function ($payload) {
                return collect($payload['custom_attributes'])->contains(fn ($attr) =>
                    $attr['attribute_code'] === 'description' && $attr['value'] === 'Manual override'
                );
            }))
            ->andReturn(['sku' => 'EXISTING-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $result = $sync->sync();

        // Should have performed an update
        $this->assertGreaterThanOrEqual(1, $result['updated']);

        // value_live should now match the override
        $updated = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $descAttr->id)
            ->first();
        $this->assertEquals('Manual override', $updated->value_live);
    }

    #[Test]
    public function test_skips_attributes_with_is_sync_disabled(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'EXISTING-001',
        ]);

        $internalAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'internal_notes',
            'data_type' => 'text',
            'is_sync' => 'no',
            'editable' => 'yes',
            'needs_approval' => 'no',
        ]);

        // Use upsertVersioned instead of writeVersioned
        $this->eavWriter->upsertVersioned($entity->id, $internalAttr->id, 'Internal notes');

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('EXISTING-001')
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        // Should not call updateProduct at all since no synced attributes have changes
        // (internal_notes is is_sync='no')
        $this->magentoClient->shouldNotReceive('updateProduct');

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $result = $sync->sync();

        // No attributes to sync -> push phase skipped
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
    }

    #[Test]
    public function test_syncs_single_product_by_sku(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuSingle,
        ]);

        // getProduct is called twice: once during pull, once during push
        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuSingle)
            ->twice()
            ->andReturn(['sku' => 'SINGLE-001']);

        // Should not call getProducts for all products
        $this->magentoClient->shouldNotReceive('getProducts');

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, 'SINGLE-001', $this->syncRun);
        $result = $sync->sync();

        // Assert that stats array is returned
        $this->assertIsArray($result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    #[Test]
    public function test_logs_sync_results_to_database(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => $this->skuTest,
        ]);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => $this->skuTest]]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with($this->skuTest)
            ->once()
            ->andReturn(['sku' => 'TEST-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        // Should have logged sync result
        $this->assertDatabaseHas('sync_results', [
            'sync_run_id' => $this->syncRun->id,
            'entity_id' => $entity->id,
            'item_identifier' => $this->skuTest,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

