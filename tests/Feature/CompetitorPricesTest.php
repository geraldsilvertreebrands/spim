<?php

namespace Tests\Feature;

use App\Filament\PricingPanel\Pages\CompetitorPrices;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceScrape;
use App\Models\User;
use App\Services\EavWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompetitorPricesTest extends TestCase
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

        // Create product entity type (or get existing one)
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
            'data_type' => 'text', // Store as text since decimal is not in enum
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

    public function test_competitor_prices_page_loads_for_authenticated_pricing_user(): void
    {
        $this->actingAs($this->pricingUser);

        Livewire::test(CompetitorPrices::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_competitor_prices_page_loads_for_admin_user(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(CompetitorPrices::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_page_displays_empty_state_when_no_price_data(): void
    {
        $this->actingAs($this->pricingUser);

        Livewire::test(CompetitorPrices::class)
            ->assertSet('productPrices', [])
            ->assertSet('competitors', [])
            ->assertSee('No competitor pricing data available');
    }

    public function test_page_displays_product_with_competitor_prices(): void
    {
        $this->actingAs($this->pricingUser);

        // Create a product
        $product = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);

        // Set product attributes
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

        Livewire::test(CompetitorPrices::class)
            ->assertSet('loading', false)
            ->assertSee('Organic Coconut Oil')
            ->assertSee('Wellness Warehouse')
            ->assertSee('Takealot')
            ->assertDontSee('No competitor pricing data available');
    }

    public function test_page_displays_correct_competitors_list(): void
    {
        $this->actingAs($this->pricingUser);

        // Create products with different competitors
        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        PriceScrape::factory()->create([
            'product_id' => $product1->id,
            'competitor_name' => 'Wellness Warehouse',
            'price' => 100.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product2->id,
            'competitor_name' => 'Takealot',
            'price' => 120.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product2->id,
            'competitor_name' => 'Checkers',
            'price' => 110.00,
            'scraped_at' => now(),
        ]);

        Livewire::test(CompetitorPrices::class)
            ->assertSet('competitors', ['Checkers', 'Takealot', 'Wellness Warehouse']); // Sorted alphabetically
    }

    public function test_page_calculates_price_position_as_cheapest(): void
    {
        $this->actingAs($this->pricingUser);

        // Create a product where we're cheapest
        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product, [
            'name' => 'Product A',
            'price' => 80.00, // Cheapest
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 1',
            'price' => 90.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 2',
            'price' => 95.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(CompetitorPrices::class);

        $productPrices = $component->get('productPrices');
        $this->assertCount(1, $productPrices);
        $this->assertEquals('cheapest', $productPrices[0]['position']);
        $this->assertFalse($productPrices[0]['is_more_expensive']);
    }

    public function test_page_calculates_price_position_as_most_expensive(): void
    {
        $this->actingAs($this->pricingUser);

        // Create a product where we're most expensive
        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product, [
            'name' => 'Product B',
            'price' => 100.00, // Most expensive
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 1',
            'price' => 80.00,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 2',
            'price' => 85.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(CompetitorPrices::class);

        $productPrices = $component->get('productPrices');
        $this->assertCount(1, $productPrices);
        $this->assertEquals('most_expensive', $productPrices[0]['position']);
        $this->assertTrue($productPrices[0]['is_more_expensive']);
    }

    public function test_page_calculates_price_position_as_middle(): void
    {
        $this->actingAs($this->pricingUser);

        // Create a product where we're in the middle
        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product, [
            'name' => 'Product C',
            'price' => 90.00, // Middle
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 1',
            'price' => 80.00, // Cheapest
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 2',
            'price' => 100.00, // Most expensive
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(CompetitorPrices::class);

        $productPrices = $component->get('productPrices');
        $this->assertCount(1, $productPrices);
        $this->assertEquals('middle', $productPrices[0]['position']);
        $this->assertTrue($productPrices[0]['is_more_expensive']); // We're more expensive than cheapest
    }

    public function test_page_calculates_price_difference_correctly(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product, [
            'name' => 'Product D',
            'price' => 95.00,
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 1',
            'price' => 85.00, // Cheapest competitor
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Competitor 2',
            'price' => 100.00,
            'scraped_at' => now(),
        ]);

        $component = Livewire::test(CompetitorPrices::class);

        $productPrices = $component->get('productPrices');
        $this->assertCount(1, $productPrices);
        $this->assertEquals(10.00, $productPrices[0]['price_difference']); // 95 - 85 = 10
    }

    public function test_category_filter_works(): void
    {
        $this->actingAs($this->pricingUser);

        // Create products in different categories
        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, [
            'name' => 'Product Oils',
            'price' => 100.00,
            'category' => 'Oils',
        ]);

        $this->setEntityAttributes($product2, [
            'name' => 'Product Supplements',
            'price' => 200.00,
            'category' => 'Supplements',
        ]);

        PriceScrape::factory()->create(['product_id' => $product1->id, 'competitor_name' => 'Comp1', 'price' => 95.00]);
        PriceScrape::factory()->create(['product_id' => $product2->id, 'competitor_name' => 'Comp1', 'price' => 195.00]);

        $component = Livewire::test(CompetitorPrices::class)
            ->call('updateCategory', 'Oils');

        $productPrices = $component->get('productPrices');
        $this->assertCount(1, $productPrices);
        $this->assertEquals('Product Oils', $productPrices[0]['name']);
    }

    public function test_sorting_by_name_works(): void
    {
        $this->actingAs($this->pricingUser);

        // Create products with different names
        $productB = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $productA = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($productB, ['name' => 'B Product', 'price' => 100.00]);
        $this->setEntityAttributes($productA, ['name' => 'A Product', 'price' => 200.00]);

        PriceScrape::factory()->create(['product_id' => $productB->id, 'competitor_name' => 'Comp1', 'price' => 95.00]);
        PriceScrape::factory()->create(['product_id' => $productA->id, 'competitor_name' => 'Comp1', 'price' => 195.00]);

        $component = Livewire::test(CompetitorPrices::class)
            ->call('updateSort', 'name', 'asc');

        $productPrices = $component->get('productPrices');
        $this->assertEquals('A Product', $productPrices[0]['name']);
        $this->assertEquals('B Product', $productPrices[1]['name']);
    }

    public function test_sorting_by_price_difference_works(): void
    {
        $this->actingAs($this->pricingUser);

        $product1 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);
        $product2 = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product1, ['name' => 'Product 1', 'price' => 105.00]);
        $this->setEntityAttributes($product2, ['name' => 'Product 2', 'price' => 120.00]);

        // Product 1: diff = 105 - 100 = 5
        PriceScrape::factory()->create(['product_id' => $product1->id, 'competitor_name' => 'Comp1', 'price' => 100.00]);

        // Product 2: diff = 120 - 100 = 20
        PriceScrape::factory()->create(['product_id' => $product2->id, 'competitor_name' => 'Comp1', 'price' => 100.00]);

        $component = Livewire::test(CompetitorPrices::class)
            ->call('updateSort', 'price_difference', 'desc');

        $productPrices = $component->get('productPrices');
        $this->assertEquals('Product 2', $productPrices[0]['name']); // Larger difference first
        $this->assertEquals('Product 1', $productPrices[1]['name']);
    }

    public function test_refresh_reloads_data(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(CompetitorPrices::class)
            ->call('refresh')
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_format_price_method(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(CompetitorPrices::class);
        $instance = $component->instance();

        $this->assertEquals('R100.00', $instance->formatPrice(100.00));
        $this->assertEquals('R0.99', $instance->formatPrice(0.99));
        $this->assertEquals('-', $instance->formatPrice(null));
    }

    public function test_position_badge_class_returns_correct_classes(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(CompetitorPrices::class);
        $instance = $component->instance();

        $this->assertStringContainsString('green', $instance->getPositionBadgeClass('cheapest'));
        $this->assertStringContainsString('yellow', $instance->getPositionBadgeClass('middle'));
        $this->assertStringContainsString('red', $instance->getPositionBadgeClass('most_expensive'));
        $this->assertStringContainsString('gray', $instance->getPositionBadgeClass('unknown'));
    }

    public function test_position_label_returns_correct_labels(): void
    {
        $this->actingAs($this->pricingUser);

        $component = Livewire::test(CompetitorPrices::class);
        $instance = $component->instance();

        $this->assertEquals('Cheapest', $instance->getPositionLabel('cheapest'));
        $this->assertEquals('Mid-Range', $instance->getPositionLabel('middle'));
        $this->assertEquals('Most Expensive', $instance->getPositionLabel('most_expensive'));
        $this->assertEquals('Unknown', $instance->getPositionLabel('unknown'));
    }

    public function test_page_highlights_products_where_we_are_more_expensive(): void
    {
        $this->actingAs($this->pricingUser);

        $product = Entity::factory()->create(['entity_type_id' => $this->productType->id]);

        $this->setEntityAttributes($product, ['name' => 'Expensive Product', 'price' => 100.00]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Cheaper Competitor',
            'price' => 80.00,
            'scraped_at' => now(),
        ]);

        Livewire::test(CompetitorPrices::class)
            ->assertSee('Expensive Product')
            ->assertSeeHtml('bg-red-50'); // Highlighted row
    }
}
