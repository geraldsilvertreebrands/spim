<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Services\EavWriter;
use App\Services\MagentoApiClient;
use App\Services\Sync\ProductSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductSyncConversionTest extends TestCase
{
    use RefreshDatabase;

    private ProductSync $productSync;

    private EntityType $entityType;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the seeded entity type from TestBaseSeeder to avoid concurrent inserts
        $this->entityType = EntityType::where('name', 'product')->firstOrFail();

        // Create a minimal ProductSync instance for testing
        $magentoClient = $this->createMock(MagentoApiClient::class);
        $eavWriter = app(EavWriter::class);

        $this->productSync = new ProductSync(
            $magentoClient,
            $eavWriter,
            $this->entityType
        );
    }

    #[Test]
    public function test_convert_value_to_string_handles_select_as_plain_string(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueToString');
        $method->setAccessible(true);

        // Test select attribute (plain string)
        $result = $method->invoke($this->productSync, '16802', 'select');
        $this->assertEquals('16802', $result);

        // Test integer select value
        $result = $method->invoke($this->productSync, 16802, 'select');
        $this->assertEquals('16802', $result);
    }

    #[Test]
    public function test_convert_value_to_string_handles_multiselect_comma_separated(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueToString');
        $method->setAccessible(true);

        // Test multiselect attribute (comma-separated from Magento)
        $result = $method->invoke($this->productSync, '16802,16722', 'multiselect');
        $this->assertEquals('["16802","16722"]', $result);
    }

    #[Test]
    public function test_convert_value_to_string_handles_multiselect_with_spaces(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueToString');
        $method->setAccessible(true);

        // Test multiselect with spaces (should trim)
        $result = $method->invoke($this->productSync, '16802, 16722, 16823', 'multiselect');
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(['16802', '16722', '16823'], $decoded);
    }

    #[Test]
    public function test_convert_value_to_string_handles_multiselect_already_array(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueToString');
        $method->setAccessible(true);

        // Test multiselect when Magento returns array (some APIs do this)
        $result = $method->invoke($this->productSync, ['16802', '16722'], 'multiselect');
        $this->assertEquals('["16802","16722"]', $result);
    }

    #[Test]
    public function test_convert_value_for_magento_handles_select_as_string(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueForMagento');
        $method->setAccessible(true);

        $attr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'color',
            'data_type' => 'select',
        ]);

        // Test select export (stored as string, should return as-is)
        $result = $method->invoke($this->productSync, '16802', $attr);
        $this->assertEquals('16802', $result);
    }

    #[Test]
    public function test_convert_value_for_magento_handles_multiselect_to_comma_separated(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueForMagento');
        $method->setAccessible(true);

        $attr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'category_ids',
            'data_type' => 'multiselect',
        ]);

        // Test multiselect export (stored as JSON, should return comma-separated)
        $result = $method->invoke($this->productSync, '["16802","16722"]', $attr);
        $this->assertEquals('16802,16722', $result);
    }

    #[Test]
    public function test_convert_value_for_magento_handles_single_multiselect_value(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueForMagento');
        $method->setAccessible(true);

        $attr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'category_ids',
            'data_type' => 'multiselect',
        ]);

        // Test single value multiselect
        $result = $method->invoke($this->productSync, '["16802"]', $attr);
        $this->assertEquals('16802', $result);
    }

    #[Test]
    public function test_convert_value_to_string_handles_null(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueToString');
        $method->setAccessible(true);

        $result = $method->invoke($this->productSync, null, 'select');
        $this->assertNull($result);

        $result = $method->invoke($this->productSync, null, 'multiselect');
        $this->assertNull($result);
    }

    #[Test]
    public function test_convert_value_for_magento_handles_null(): void
    {
        $reflection = new \ReflectionClass($this->productSync);
        $method = $reflection->getMethod('convertValueForMagento');
        $method->setAccessible(true);

        $attr = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'test',
            'data_type' => 'select',
        ]);

        $result = $method->invoke($this->productSync, null, $attr);
        $this->assertNull($result);
    }
}
