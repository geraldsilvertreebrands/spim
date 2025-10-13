<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\SyncRun;
use App\Services\EavWriter;
use App\Services\MagentoApiClient;
use App\Services\Sync\ProductSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductSyncMinimalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_sync_with_one_attribute_shows_all_calls(): void
    {
        $entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product', 'description' => 'Test product type']
        );
        
        $syncRun = SyncRun::factory()->forSchedule()->create([
            'entity_type_id' => $entityType->id,
            'sync_type' => 'products',
        ]);

        // Create one attribute
        $nameAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'name',
            'data_type' => 'text',
            'is_sync' => 'from_external',
            'editable' => 'no',
        ]);

        $magentoClient = Mockery::mock(MagentoApiClient::class);
        $magentoClient->shouldIgnoreMissing(); // Allow any calls, log them
        
        $eavWriter = app(EavWriter::class);

        // Set up expected calls
        $magentoClient->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['frontend_input' => 'text', 'backend_type' => 'varchar']);

        $magentoClient->shouldReceive('getProducts')
            ->andReturn(['items' => [
                ['sku' => 'TEST-001', 'name' => 'Test Product', 'custom_attributes' => []]
            ]]);

        $magentoClient->shouldReceive('getProduct')
            ->with('TEST-001')
            ->andReturn(['sku' => 'TEST-001']);

        $sync = new ProductSync($magentoClient, $eavWriter, $entityType, null, $syncRun);
        
        try {
            $result = $sync->sync();
            $this->assertTrue(true, "Sync completed successfully");
        } catch (\Exception $e) {
            $this->fail("Sync failed: " . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}


