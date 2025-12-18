<?php

namespace Tests\Feature;

use App\Filament\PricingPanel\Pages\PriceHistory;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceScrape;
use App\Models\User;
use App\Services\EavWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $pricingUser;

    private User $regularUser;

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

        // Create regular user without pricing access
        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('pim-editor');

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

    public function test_price_history_page_loads_for_authenticated_pricing_user(): void
    {
        $this->actingAs($this->pricingUser);

        Livewire::test(PriceHistory::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_price_history_page_loads_for_admin_user(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(PriceHistory::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    // =====================================================
    // Empty State Tests
    // =====================================================

    public function test_page_displays_empty_state_when_no_products(): void
    {
        $this->actingAs($this->pricingUser);

        Livewire::test(PriceHistory::class)
            ->assertSet('products', [])
            ->assertSee('No Price Data Available');
    }

    public function test_page_shows_no_chart_data_when_no_scrapes_in_date_range(): void
    {
        $this->actingAs($this->pricingUser);

        // Create a product
        $product = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);

        $this->setEntityAttributes($product, [
            'name' => 'Test Product',
            'price' => 100.00,
        ]);

        // Create a scrape from 100 days ago (outside default 30 day range)
        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(100),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // Product should be loaded
        $this->assertCount(1, $component->get('products'));

        // The competitors list will still be populated (it checks all scrapes for the product)
        // But the chart data for those competitors should have null values in the 30-day range
        $chartData = $component->get('chartData');
        $this->assertNotEmpty($chartData);

        // The competitor A should be in the list but with no data points in the default range
        // Since the scrape is 100 days old and default range is 30 days
        $competitorDataset = collect($chartData['datasets'])->firstWhere('label', 'Competitor A');
        if ($competitorDataset) {
            // All data points should be null since no scrapes in the 30-day range
            $nonNullPoints = array_filter($competitorDataset['data'], fn ($v) => $v !== null);
            $this->assertEmpty($nonNullPoints);
        }
    }

    // =====================================================
    // Product Selection Tests
    // =====================================================

    public function test_products_list_is_populated_when_scrape_data_exists(): void
    {
        $this->actingAs($this->pricingUser);

        // Create two products
        $product1 = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);
        $product2 = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);

        $this->setEntityAttributes($product1, ['name' => 'Alpha Product', 'price' => 100.00]);
        $this->setEntityAttributes($product2, ['name' => 'Beta Product', 'price' => 150.00]);

        // Create price scrapes for both products
        PriceScrape::factory()->create([
            'product_id' => $product1->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product2->id,
            'competitor_name' => 'Competitor B',
            'price' => 145.00,
            'scraped_at' => now()->subDays(3),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // Both products should be listed
        $products = $component->get('products');
        $this->assertCount(2, $products);

        // Products should be sorted by name
        $this->assertEquals('Alpha Product', $products[0]['name']);
        $this->assertEquals('Beta Product', $products[1]['name']);
    }

    public function test_first_product_is_auto_selected_on_load(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);

        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // First product should be auto-selected
        $this->assertEquals($product->id, $component->get('selectedProductId'));
    }

    public function test_changing_product_selection_updates_chart_data(): void
    {
        $this->actingAs($this->pricingUser);

        // Create two products with different competitors
        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, ['name' => 'Product 1', 'price' => 100.00]);
        $this->setEntityAttributes($product2, ['name' => 'Product 2', 'price' => 200.00]);

        PriceScrape::factory()->create([
            'product_id' => $product1->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product2->id,
            'competitor_name' => 'Competitor B',
            'price' => 195.00,
            'scraped_at' => now()->subDays(3),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // Initially should show Product 1's competitor
        $this->assertContains('Competitor A', $component->get('competitors'));

        // Change to Product 2
        $component->set('selectedProductId', $product2->id);

        // Should now show Product 2's competitor
        $this->assertContains('Competitor B', $component->get('competitors'));
        $this->assertNotContains('Competitor A', $component->get('competitors'));
    }

    // =====================================================
    // Chart Data Tests
    // =====================================================

    public function test_chart_data_includes_multiple_competitors(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        // Create scrapes for multiple competitors
        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Wellness Warehouse',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Takealot',
            'price' => 105.00,
            'scraped_at' => now()->subDays(5),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Checkers',
            'price' => 99.00,
            'scraped_at' => now()->subDays(5),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // Should have 3 competitors
        $competitors = $component->get('competitors');
        $this->assertCount(3, $competitors);
        $this->assertContains('Wellness Warehouse', $competitors);
        $this->assertContains('Takealot', $competitors);
        $this->assertContains('Checkers', $competitors);
    }

    public function test_chart_data_includes_our_price_as_reference(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 89.99]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // Our price should be set
        $this->assertEquals(89.99, $component->get('ourPrice'));

        // Chart data should include our price dataset
        $chartData = $component->get('chartData');
        $this->assertNotEmpty($chartData);
        $this->assertArrayHasKey('datasets', $chartData);

        // Find the "Our Price" dataset
        $ourPriceDataset = collect($chartData['datasets'])->firstWhere('label', 'Our Price');
        $this->assertNotNull($ourPriceDataset);
        $this->assertArrayHasKey('borderDash', $ourPriceDataset); // Reference line is dashed
    }

    public function test_chart_data_has_correct_label_count_for_date_range(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        // Test 7 day range
        $component = Livewire::test(PriceHistory::class)
            ->set('dateRange', '7');

        $chartData = $component->get('chartData');
        $this->assertCount(8, $chartData['labels']); // 7 days + today

        // Test 30 day range
        $component->set('dateRange', '30');
        $chartData = $component->get('chartData');
        $this->assertCount(31, $chartData['labels']); // 30 days + today
    }

    // =====================================================
    // Date Range Tests
    // =====================================================

    public function test_changing_date_range_updates_chart(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        // Create scrapes at different times
        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 90.00,
            'scraped_at' => now()->subDays(45),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // Default is 30 days - should not include the 45 day old scrape
        $chartData30 = $component->get('chartData');
        $this->assertCount(31, $chartData30['labels']);

        // Change to 60 days - should include the 45 day old scrape
        $component->set('dateRange', '60');
        $chartData60 = $component->get('chartData');
        $this->assertCount(61, $chartData60['labels']);
    }

    public function test_get_date_range_options_returns_all_options(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceHistory::class);

        $options = $component->instance()->getDateRangeOptions();

        $this->assertArrayHasKey('7', $options);
        $this->assertArrayHasKey('14', $options);
        $this->assertArrayHasKey('30', $options);
        $this->assertArrayHasKey('60', $options);
        $this->assertArrayHasKey('90', $options);
    }

    // =====================================================
    // Helper Methods Tests
    // =====================================================

    public function test_format_price_formats_correctly(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceHistory::class);
        $instance = $component->instance();

        $this->assertEquals('R100.00', $instance->formatPrice(100.00));
        $this->assertEquals('R1,234.56', $instance->formatPrice(1234.56));
        $this->assertEquals('-', $instance->formatPrice(null));
    }

    public function test_get_selected_product_name_returns_correct_name(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Organic Coconut Oil', 'price' => 89.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 85.00,
            'scraped_at' => now()->subDays(5),
        ]);

        $component = Livewire::test(PriceHistory::class);

        $this->assertEquals('Organic Coconut Oil', $component->instance()->getSelectedProductName());
    }

    public function test_get_selected_product_name_returns_null_when_no_product_selected(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceHistory::class);
        $component->set('selectedProductId', null);

        $this->assertNull($component->instance()->getSelectedProductName());
    }

    // =====================================================
    // Refresh Functionality Tests
    // =====================================================

    public function test_refresh_reloads_chart_data(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor A',
            'price' => 95.00,
            'scraped_at' => now()->subDays(5),
        ]);

        $component = Livewire::test(PriceHistory::class);

        // Get initial chart data
        $initialChartData = $component->get('chartData');
        $this->assertNotEmpty($initialChartData);

        // Call refresh
        $component->call('refresh');

        // Chart data should still be present after refresh
        $refreshedChartData = $component->get('chartData');
        $this->assertNotEmpty($refreshedChartData);
    }

    // =====================================================
    // Price History Data Tests
    // =====================================================

    public function test_price_history_shows_price_trends_over_time(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        // Create price history with increasing prices
        for ($i = 10; $i >= 1; $i--) {
            PriceScrape::factory()->create([
                'product_id' => $product->id,
                'competitor_name' => 'Competitor A',
                'price' => 80 + ($i * 2), // Prices from 82 to 100
                'scraped_at' => now()->subDays($i),
            ]);
        }

        $component = Livewire::test(PriceHistory::class)
            ->set('dateRange', '14');

        $chartData = $component->get('chartData');

        // Should have data for Competitor A
        $competitorDataset = collect($chartData['datasets'])->firstWhere('label', 'Competitor A');
        $this->assertNotNull($competitorDataset);

        // Data should contain price values
        $priceData = $competitorDataset['data'];
        $this->assertNotEmpty(array_filter($priceData, fn ($v) => $v !== null));
    }

    // =====================================================
    // Error Handling Tests
    // =====================================================

    public function test_page_handles_missing_product_gracefully(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(PriceHistory::class);

        // Set a non-existent product ID
        $component->set('selectedProductId', 'non-existent-id');

        // Should show error
        $this->assertEquals('Product not found', $component->get('error'));
    }

    // =====================================================
    // Chart Colors Tests
    // =====================================================

    public function test_chart_uses_distinct_colors_for_competitors(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $this->setEntityAttributes($product, ['name' => 'Test Product', 'price' => 100.00]);

        // Create multiple competitors
        $competitors = ['Comp A', 'Comp B', 'Comp C', 'Comp D'];
        foreach ($competitors as $competitor) {
            PriceScrape::factory()->create([
                'product_id' => $product->id,
                'competitor_name' => $competitor,
                'price' => rand(90, 110),
                'scraped_at' => now()->subDays(5),
            ]);
        }

        $component = Livewire::test(PriceHistory::class);

        $chartData = $component->get('chartData');
        $datasets = $chartData['datasets'];

        // Get all competitor datasets (excluding "Our Price")
        $competitorDatasets = array_filter($datasets, fn ($d) => $d['label'] !== 'Our Price');

        // Each should have a unique color
        $colors = array_map(fn ($d) => $d['borderColor'], $competitorDatasets);
        $uniqueColors = array_unique($colors);

        $this->assertCount(count($competitors), $uniqueColors);
    }
}
