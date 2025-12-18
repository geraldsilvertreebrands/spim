<?php

namespace Tests\Feature;

use App\Filament\PricingPanel\Pages\PriceComparisonMatrix;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceScrape;
use App\Models\User;
use App\Services\EavWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceComparisonMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $pricingUser;

    private EntityType $productType;

    private Attribute $nameAttr;

    private Attribute $priceAttr;

    private Attribute $categoryAttr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Create admin user
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Create pricing analyst user
        $this->pricingUser = User::factory()->create();
        $this->pricingUser->assignRole('pricing-analyst');

        // Create product entity type
        $this->productType = EntityType::firstOrCreate(
            ['name' => 'Product'],
            ['display_name' => 'Product', 'description' => 'Product entity type']
        );

        // Create attributes for products
        $this->nameAttr = Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'name',
            'data_type' => 'text',
            'needs_approval' => 'no',
        ]);

        $this->priceAttr = Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'price',
            'data_type' => 'text',
            'needs_approval' => 'no',
        ]);

        $this->categoryAttr = Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'category',
            'data_type' => 'text',
            'needs_approval' => 'no',
        ]);
    }

    /**
     * Helper method to set entity attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function setEntityAttributes(Entity $entity, array $attributes): void
    {
        $writer = app(EavWriter::class);

        foreach ($attributes as $name => $value) {
            $attr = match ($name) {
                'name' => $this->nameAttr,
                'price' => $this->priceAttr,
                'category' => $this->categoryAttr,
                default => null,
            };

            if ($attr) {
                $writer->upsertVersioned($entity->id, $attr->id, $value, [
                    'confidence' => 1.0,
                ]);
            }
        }
    }

    // =====================================================
    // Authentication and Authorization Tests
    // =====================================================

    public function test_price_comparison_matrix_page_loads_for_authenticated_pricing_user(): void
    {
        $this->actingAs($this->pricingUser);

        Livewire::test(PriceComparisonMatrix::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_price_comparison_matrix_page_loads_for_admin_user(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(PriceComparisonMatrix::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    // =====================================================
    // Empty State Tests
    // =====================================================

    public function test_page_displays_empty_state_when_no_price_data(): void
    {
        $this->actingAs($this->pricingUser);

        Livewire::test(PriceComparisonMatrix::class)
            ->assertSet('matrixData', [])
            ->assertSet('competitors', [])
            ->assertSee('No Price Data Available');
    }

    // =====================================================
    // Matrix Data Tests
    // =====================================================

    public function test_matrix_displays_product_with_competitor_prices(): void
    {
        $this->actingAs($this->pricingUser);

        // Create a product
        $product = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);

        $this->setEntityAttributes($product, [
            'name' => 'Organic Coconut Oil',
            'price' => 89.00,
            'category' => 'Oils',
        ]);

        // Create competitor price scrapes
        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Wellness Warehouse',
            'price' => 85.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Takealot',
            'price' => 92.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(PriceComparisonMatrix::class);

        // Should have matrix data
        $matrixData = $component->get('matrixData');
        $this->assertCount(1, $matrixData);

        // Check product data
        $row = $matrixData[0];
        $this->assertEquals('Organic Coconut Oil', $row['name']);
        $this->assertEquals(89.00, $row['our_price']);
        $this->assertArrayHasKey('Wellness Warehouse', $row['competitor_prices']);
        $this->assertEquals(85.00, $row['competitor_prices']['Wellness Warehouse']);
        $this->assertEquals(92.00, $row['competitor_prices']['Takealot']);
    }

    public function test_matrix_displays_multiple_products(): void
    {
        $this->actingAs($this->pricingUser);

        // Create two products
        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, ['name' => 'Product A', 'price' => 100.00]);
        $this->setEntityAttributes($product2, ['name' => 'Product B', 'price' => 200.00]);

        // Create price scrapes
        PriceScrape::factory()->create([
            'product_id' => $product1->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product2->id,
            'competitor_name' => 'Competitor A',
            'price' => 195.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(PriceComparisonMatrix::class);

        $matrixData = $component->get('matrixData');
        $this->assertCount(2, $matrixData);
    }

    // =====================================================
    // Price Position Tests
    // =====================================================

    public function test_price_position_is_cheapest_when_we_have_lowest_price(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 80.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 90.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor B',
            'price' => 95.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(PriceComparisonMatrix::class);

        $matrixData = $component->get('matrixData');
        $this->assertEquals('cheapest', $matrixData[0]['position']);
    }

    public function test_price_position_is_most_expensive_when_we_have_highest_price(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 85.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor B',
            'price' => 90.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(PriceComparisonMatrix::class);

        $matrixData = $component->get('matrixData');
        $this->assertEquals('most_expensive', $matrixData[0]['position']);
    }

    public function test_price_position_is_middle_when_we_are_mid_range(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 90.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 85.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor B',
            'price' => 95.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(PriceComparisonMatrix::class);

        $matrixData = $component->get('matrixData');
        $this->assertEquals('middle', $matrixData[0]['position']);
    }

    // =====================================================
    // Color Coding Tests
    // =====================================================

    public function test_get_cell_color_class_returns_green_when_cheaper(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        $colorClass = $instance->getCellColorClass(80.00, 90.00);
        $this->assertStringContainsString('green', $colorClass);
    }

    public function test_get_cell_color_class_returns_red_when_more_expensive(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        $colorClass = $instance->getCellColorClass(100.00, 90.00);
        $this->assertStringContainsString('red', $colorClass);
    }

    public function test_get_cell_color_class_returns_yellow_when_same_price(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        $colorClass = $instance->getCellColorClass(90.00, 90.00);
        $this->assertStringContainsString('yellow', $colorClass);
    }

    public function test_get_cell_color_class_returns_gray_when_no_competitor_price(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        $colorClass = $instance->getCellColorClass(90.00, null);
        $this->assertStringContainsString('gray', $colorClass);
    }

    public function test_get_our_price_cell_color_class_returns_correct_colors(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        $this->assertStringContainsString('green', $instance->getOurPriceCellColorClass('cheapest'));
        $this->assertStringContainsString('yellow', $instance->getOurPriceCellColorClass('middle'));
        $this->assertStringContainsString('red', $instance->getOurPriceCellColorClass('most_expensive'));
    }

    // =====================================================
    // Sorting Tests
    // =====================================================

    public function test_sorting_by_name_works(): void
    {
        $this->actingAs($this->pricingUser);

        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, ['name' => 'Zebra Product', 'price' => 100.00]);
        $this->setEntityAttributes($product2, ['name' => 'Alpha Product', 'price' => 100.00]);

        PriceScrape::factory()->create(['product_id' => $product1->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);
        PriceScrape::factory()->create(['product_id' => $product2->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);

        $component = Livewire::test(PriceComparisonMatrix::class)
            ->call('updateSort', 'name', 'asc');

        $matrixData = $component->get('matrixData');
        $this->assertEquals('Alpha Product', $matrixData[0]['name']);
        $this->assertEquals('Zebra Product', $matrixData[1]['name']);
    }

    public function test_sorting_by_price_works(): void
    {
        $this->actingAs($this->pricingUser);

        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, ['name' => 'Product A', 'price' => 150.00]);
        $this->setEntityAttributes($product2, ['name' => 'Product B', 'price' => 100.00]);

        PriceScrape::factory()->create(['product_id' => $product1->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);
        PriceScrape::factory()->create(['product_id' => $product2->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);

        $component = Livewire::test(PriceComparisonMatrix::class)
            ->call('updateSort', 'our_price', 'asc');

        $matrixData = $component->get('matrixData');
        $this->assertEquals(100.00, $matrixData[0]['our_price']);
        $this->assertEquals(150.00, $matrixData[1]['our_price']);
    }

    // =====================================================
    // Category Filter Tests
    // =====================================================

    public function test_category_filter_works(): void
    {
        $this->actingAs($this->pricingUser);

        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, ['name' => 'Product A', 'price' => 100.00, 'category' => 'Oils']);
        $this->setEntityAttributes($product2, ['name' => 'Product B', 'price' => 100.00, 'category' => 'Supplements']);

        PriceScrape::factory()->create(['product_id' => $product1->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);
        PriceScrape::factory()->create(['product_id' => $product2->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);

        $component = Livewire::test(PriceComparisonMatrix::class)
            ->call('updateCategory', 'Oils');

        $matrixData = $component->get('matrixData');
        $this->assertCount(1, $matrixData);
        $this->assertEquals('Product A', $matrixData[0]['name']);
    }

    public function test_get_categories_returns_all_unique_categories(): void
    {
        $this->actingAs($this->pricingUser);

        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, ['name' => 'Product A', 'price' => 100.00, 'category' => 'Oils']);
        $this->setEntityAttributes($product2, ['name' => 'Product B', 'price' => 100.00, 'category' => 'Supplements']);

        PriceScrape::factory()->create(['product_id' => $product1->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);
        PriceScrape::factory()->create(['product_id' => $product2->id, 'competitor_name' => 'Comp', 'price' => 95.00, 'scraped_at' => now()]);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $categories = $component->instance()->getCategories();

        $this->assertArrayHasKey('all', $categories);
        $this->assertContains('Oils', $categories);
        $this->assertContains('Supplements', $categories);
    }

    // =====================================================
    // Helper Methods Tests
    // =====================================================

    public function test_format_price_formats_correctly(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        $this->assertEquals('R100.00', $instance->formatPrice(100.00));
        $this->assertEquals('R1,234.56', $instance->formatPrice(1234.56));
        $this->assertEquals('-', $instance->formatPrice(null));
    }

    public function test_get_price_difference_shows_correct_difference(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        // We're more expensive
        $diff = $instance->getPriceDifference(100.00, 90.00);
        $this->assertStringContainsString('+R10.00', $diff);
        $this->assertStringContainsString('more expensive', $diff);

        // We're cheaper
        $diff = $instance->getPriceDifference(80.00, 90.00);
        $this->assertStringContainsString('R-10.00', $diff);
        $this->assertStringContainsString('cheaper', $diff);

        // Same price
        $diff = $instance->getPriceDifference(90.00, 90.00);
        $this->assertStringContainsString('Same', $diff);

        // No competitor price
        $diff = $instance->getPriceDifference(90.00, null);
        $this->assertEquals('No data', $diff);
    }

    // =====================================================
    // Refresh Functionality Tests
    // =====================================================

    public function test_refresh_reloads_matrix_data(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(PriceComparisonMatrix::class);

        // Get initial data
        $initialData = $component->get('matrixData');
        $this->assertNotEmpty($initialData);

        // Call refresh
        $component->call('refresh');

        // Data should still be present after refresh
        $refreshedData = $component->get('matrixData');
        $this->assertNotEmpty($refreshedData);
    }
}
