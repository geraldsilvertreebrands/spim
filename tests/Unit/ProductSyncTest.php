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
use Mockery;
use Tests\TestCase;

class ProductSyncTest extends TestCase
{
    use RefreshDatabase;

    private EntityType $entityType;
    private MagentoApiClient $magentoClient;
    private EavWriter $eavWriter;
    private SyncRun $syncRun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product', 'description' => 'Test product type']
        );
        $this->syncRun = SyncRun::factory()->forSchedule()->create([
            'entity_type_id' => $this->entityType->id,
            'sync_type' => 'products',
        ]);
        $this->magentoClient = Mockery::mock(MagentoApiClient::class);
        $this->eavWriter = app(EavWriter::class);
    }

    /** @test */
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

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'sku' => 'NEW-001',
                        'name' => 'New Product',
                        'custom_attributes' => [],
                    ],
                ],
            ]);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $result = $sync->sync();

        // Entity should be created
        $this->assertDatabaseHas('entities', [
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'NEW-001',
        ]);

        // Attribute value should be set
        $entity = Entity::where('entity_id', 'NEW-001')->first();
        $this->assertDatabaseHas('eav_input', [
            'entity_id' => $entity->id,
            'attribute_id' => $nameAttr->id,
            'value_current' => 'New Product',
        ]);

        $this->assertEquals(1, $result['stats']['created']);
    }

    /** @test */
    public function test_updates_existing_products_with_input_attributes(): void
    {
        // Create existing entity
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'EXISTING-001',
        ]);

        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        // Set initial value
        $this->eavWriter->writeInput($entity, $descAttr, 'Old description');

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'sku' => 'EXISTING-001',
                        'custom_attributes' => [
                            ['attribute_code' => 'description', 'value' => 'New description'],
                        ],
                    ],
                ],
            ]);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        // Value should be updated
        $this->assertDatabaseHas('eav_input', [
            'entity_id' => $entity->id,
            'attribute_id' => $descAttr->id,
            'value_current' => 'New description',
        ]);
    }

    /** @test */
    public function test_sets_all_three_value_fields_on_initial_import(): void
    {
        $priceAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'sku' => 'NEW-001',
                        'price' => 29.99,
                        'custom_attributes' => [],
                    ],
                ],
            ]);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        $entity = Entity::where('entity_id', 'NEW-001')->first();

        // All three value fields should be set identically
        $value = DB::table('eav_input')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $priceAttr->id)
            ->first();

        $this->assertEquals('29.99', $value->value_current);
    }

    /** @test */
    public function test_creates_products_in_magento_when_missing(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'SPIM-ONLY-001',
        ]);

        $nameAttr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'name',
            'data_type' => 'text',
            'is_sync' => 'to_external',
            'editable' => 'yes',
        ]);

        $this->eavWriter->writeVersioned($entity, $nameAttr, 'SPIM Product', needsApproval: false);

        // Mock Magento responses
        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => []]); // No products in Magento

        $this->magentoClient->shouldReceive('getProduct')
            ->with('SPIM-ONLY-001')
            ->once()
            ->andThrow(new \Exception('Product not found'));

        $this->magentoClient->shouldReceive('createProduct')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['product']['sku'] === 'SPIM-ONLY-001' &&
                       $payload['product']['status'] === 2; // disabled
            }))
            ->andReturn(['id' => 1, 'sku' => 'SPIM-ONLY-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        $this->assertEquals(1, $result['stats']['created'] ?? 0);
    }

    /** @test */
    public function test_creates_products_as_disabled(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'NEW-001',
        ]);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => []]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('NEW-001')
            ->once()
            ->andThrow(new \Exception('Product not found'));

        $this->magentoClient->shouldReceive('createProduct')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return isset($payload['product']['status']) && $payload['product']['status'] === 2;
            }))
            ->andReturn(['id' => 1, 'sku' => 'NEW-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();
    }

    /** @test */
    public function test_updates_existing_products_with_versioned_attributes(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'EXISTING-001',
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

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('EXISTING-001')
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        $this->magentoClient->shouldReceive('updateProduct')
            ->once()
            ->with('EXISTING-001', Mockery::on(function ($payload) {
                return isset($payload['product']['custom_attributes']) &&
                       collect($payload['product']['custom_attributes'])->contains(fn ($attr) =>
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

    /** @test */
    public function test_only_syncs_when_value_approved_differs_from_value_live(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'EXISTING-001',
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

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('EXISTING-001')
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        // Should NOT call updateProduct since values are the same
        $this->magentoClient->shouldNotReceive('updateProduct');

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();
    }

    /** @test */
    public function test_uses_value_override_when_present(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'EXISTING-001',
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
            'value_approved' => 'Generated desc',
            'value_override' => 'Manual override',
            'value_live' => 'Old desc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('EXISTING-001')
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        $this->magentoClient->shouldReceive('updateProduct')
            ->once()
            ->with('EXISTING-001', Mockery::on(function ($payload) {
                return collect($payload['product']['custom_attributes'])->contains(fn ($attr) =>
                    $attr['attribute_code'] === 'description' && $attr['value'] === 'Manual override'
                );
            }))
            ->andReturn(['sku' => 'EXISTING-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();
    }

    /** @test */
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
        ]);

        $this->eavWriter->writeVersioned($entity, $internalAttr, 'Internal notes', needsApproval: false);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'EXISTING-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('EXISTING-001')
            ->once()
            ->andReturn(['sku' => 'EXISTING-001']);

        // Should not include internal_notes in the update
        $this->magentoClient->shouldReceive('updateProduct')
            ->with('EXISTING-001', Mockery::on(function ($payload) {
                return !collect($payload['product']['custom_attributes'] ?? [])->contains(fn ($attr) =>
                    $attr['attribute_code'] === 'internal_notes'
                );
            }))
            ->andReturn(['sku' => 'EXISTING-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();
    }

    /** @test */
    public function test_syncs_single_product_by_sku(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'SINGLE-001',
        ]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('SINGLE-001')
            ->once()
            ->andReturn(['sku' => 'SINGLE-001']);

        // Should not call getProducts for all products
        $this->magentoClient->shouldNotReceive('getProducts');

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, 'SINGLE-001', $this->syncRun);
        $sync->sync();
    }

    /** @test */
    public function test_logs_sync_results_to_database(): void
    {
        $entity = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        $this->magentoClient->shouldReceive('getProducts')
            ->once()
            ->andReturn(['items' => [['sku' => 'TEST-001']]]);

        $this->magentoClient->shouldReceive('getProduct')
            ->with('TEST-001')
            ->once()
            ->andReturn(['sku' => 'TEST-001']);

        $sync = new ProductSync($this->magentoClient, $this->eavWriter, $this->entityType, null, $this->syncRun);
        $sync->sync();

        // Should have logged sync result
        $this->assertDatabaseHas('sync_results', [
            'sync_run_id' => $this->syncRun->id,
            'entity_id' => $entity->id,
            'item_identifier' => 'TEST-001',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

