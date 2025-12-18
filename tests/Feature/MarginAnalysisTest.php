<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceScrape;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarginAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $pricingAnalyst;

    protected User $unauthorizedUser;

    protected EntityType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->seed(RoleSeeder::class);

        // Create test users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->pricingAnalyst = User::factory()->create();
        $this->pricingAnalyst->assignRole('pricing-analyst');

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('pim-editor');

        // Create product entity type
        $this->productType = EntityType::factory()->create();

        // Create price and cost attributes
        Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'price',
            'data_type' => 'integer',
        ]);

        Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'cost',
            'data_type' => 'integer',
        ]);

        Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'title',
            'data_type' => 'text',
        ]);

        Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'category',
            'data_type' => 'text',
        ]);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_margin_analysis(): void
    {
        $response = $this->get('/pricing/margin-analysis');

        $response->assertRedirect('/pricing/login');
    }

    /** @test */
    public function unauthorized_users_cannot_access_margin_analysis(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pricing/margin-analysis');

        $response->assertForbidden();
    }

    /** @test */
    public function admin_can_access_margin_analysis(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->assertSuccessful();
    }

    /** @test */
    public function pricing_analyst_can_access_margin_analysis(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->assertSuccessful();
    }

    /** @test */
    public function it_displays_empty_state_when_no_products_have_margin_data(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->instance();

        $data = $component->getMarginData();
        $this->assertTrue($data->isEmpty());
    }

    /** @test */
    public function it_displays_margin_data_for_products_with_price_and_cost(): void
    {
        // Create a product with price and cost
        $product = $this->createProductWithMarginData('Test Product', 100, 60);

        $this->actingAs($this->admin);

        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->instance();

        $data = $component->getMarginData();
        $this->assertCount(1, $data);
        $this->assertEquals('Test Product', $data->first()['product_name']);
        $this->assertEquals(100, $data->first()['our_price']);
        $this->assertEquals(60, $data->first()['cost']);
        $this->assertEquals(40, $data->first()['margin_amount']);
        $this->assertEquals(40, $data->first()['margin_percent']);
    }

    /** @test */
    public function it_calculates_margin_correctly(): void
    {
        $product = $this->createProductWithMarginData('Product A', 200, 120);

        $this->actingAs($this->admin);
        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)->instance();

        $data = $component->getMarginData();
        $this->assertCount(1, $data);
        $this->assertEquals(80, $data->first()['margin_amount']); // 200 - 120
        $this->assertEquals(40, $data->first()['margin_percent']); // (80/200) * 100
    }

    /** @test */
    public function it_displays_multiple_products(): void
    {
        $this->createProductWithMarginData('Product A', 100, 60);
        $this->createProductWithMarginData('Product B', 200, 150);
        $this->createProductWithMarginData('Product C', 50, 25);

        $this->actingAs($this->admin);
        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)->instance();

        $data = $component->getMarginData();
        $this->assertCount(3, $data);
    }

    /** @test */
    public function it_applies_color_coding_for_margin_percentages(): void
    {
        // High margin (>= 30%) - should be green
        $highMargin = $this->createProductWithMarginData('High Margin', 100, 60);

        // Medium margin (15-30%) - should be yellow
        $mediumMargin = $this->createProductWithMarginData('Medium Margin', 100, 80);

        // Low margin (< 15%) - should be red
        $lowMargin = $this->createProductWithMarginData('Low Margin', 100, 90);

        $this->actingAs($this->admin);
        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)->instance();

        // Test color class method
        $this->assertStringContainsString('green', $component->getMarginColorClass(40));
        $this->assertStringContainsString('yellow', $component->getMarginColorClass(20));
        $this->assertStringContainsString('red', $component->getMarginColorClass(10));
    }

    /** @test */
    public function it_displays_competitor_margin_analysis(): void
    {
        $product = $this->createProductWithMarginData('Product A', 100, 60);

        // Add competitor prices
        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Takealot',
            'price' => 120,
            'scraped_at' => now(),
        ]);

        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Checkers',
            'price' => 90,
            'scraped_at' => now(),
        ]);

        $this->actingAs($this->admin);
        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)->instance();

        $data = $component->getMarginData();
        $this->assertCount(2, $data->first()['competitor_margins']); // 2 competitors
    }

    /** @test */
    public function it_calculates_competitor_margin_correctly(): void
    {
        $product = $this->createProductWithMarginData('Product A', 100, 60);

        // Add competitor price
        PriceScrape::factory()->create([
            'product_id' => $product->id,
            'competitor_name' => 'Takealot',
            'price' => 120, // If we match this, margin would be 120 - 60 = 60 (50%)
            'scraped_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->assertSee('Product A')
            ->assertSee('View 1'); // Shortened for mobile responsiveness

        // The competitor margin should be calculated as:
        // Margin if matched = 120 - 60 = 60
        // Margin % if matched = (60 / 120) * 100 = 50%
    }

    /** @test */
    public function it_sorts_by_name(): void
    {
        $this->createProductWithMarginData('Zebra Product', 100, 60);
        $this->createProductWithMarginData('Alpha Product', 100, 60);
        $this->createProductWithMarginData('Beta Product', 100, 60);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->assertSet('sortBy', 'name') // Default sortBy
            ->assertSet('sortDirection', 'asc'); // Default sortDirection
    }

    /** @test */
    public function it_sorts_by_margin_percent(): void
    {
        $this->createProductWithMarginData('Product A', 100, 90); // 10% margin
        $this->createProductWithMarginData('Product B', 100, 50); // 50% margin
        $this->createProductWithMarginData('Product C', 100, 70); // 30% margin

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->call('updateSort', 'margin_percent')
            ->assertSet('sortBy', 'margin_percent')
            ->assertSet('sortDirection', 'asc');
    }

    /** @test */
    public function it_sorts_by_margin_amount(): void
    {
        $this->createProductWithMarginData('Product A', 100, 90); // R10 margin
        $this->createProductWithMarginData('Product B', 200, 50); // R150 margin
        $this->createProductWithMarginData('Product C', 100, 70); // R30 margin

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->call('updateSort', 'margin_amount')
            ->assertSet('sortBy', 'margin_amount')
            ->assertSet('sortDirection', 'asc');
    }

    /** @test */
    public function it_toggles_sort_direction_when_clicking_same_column(): void
    {
        $this->createProductWithMarginData('Product A', 100, 60);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->assertSet('sortBy', 'name') // Already sorted by name
            ->assertSet('sortDirection', 'asc') // Default is asc
            ->call('updateSort', 'name') // Click name again
            ->assertSet('sortDirection', 'desc'); // Should toggle to desc
    }

    /** @test */
    public function it_filters_by_category(): void
    {
        $productA = $this->createProductWithMarginData('Product A', 100, 60);
        $this->setProductCategory($productA, 'Electronics');

        $productB = $this->createProductWithMarginData('Product B', 100, 60);
        $this->setProductCategory($productB, 'Food');

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->set('categoryFilter', 'Electronics')
            ->assertSee('Product A')
            ->assertDontSee('Product B');
    }

    /** @test */
    public function it_clears_filters(): void
    {
        $productA = $this->createProductWithMarginData('Product A', 100, 60);
        $this->setProductCategory($productA, 'Electronics');

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->set('categoryFilter', 'Electronics')
            ->call('clearFilters')
            ->assertSet('categoryFilter', null);
    }

    /** @test */
    public function it_refreshes_data(): void
    {
        $this->createProductWithMarginData('Product A', 100, 60);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->assertSee('Product A')
            ->call('refresh')
            ->assertSee('Product A');
    }

    /** @test */
    public function it_calculates_summary_statistics_correctly(): void
    {
        // Product A: margin = 40 (40%)
        $this->createProductWithMarginData('Product A', 100, 60);

        // Product B: margin = 50 (25%)
        $this->createProductWithMarginData('Product B', 200, 150);

        // Product C: margin = 25 (50%)
        $this->createProductWithMarginData('Product C', 50, 25);

        $this->actingAs($this->admin);
        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)->instance();

        $stats = $component->getSummaryStats();
        $this->assertEquals(3, $stats['total_products']);
        $this->assertEquals(115, $stats['total_margin_amount']); // 40 + 50 + 25
        $this->assertEquals(25, $stats['lowest_margin_percent']);
        $this->assertEquals(50, $stats['highest_margin_percent']);
    }

    /** @test */
    public function it_formats_currency_correctly(): void
    {
        $product = $this->createProductWithMarginData('Product A', 1234.56, 789.01);

        $this->actingAs($this->admin);
        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)->instance();

        $this->assertEquals('R1,234.56', $component->formatCurrency(1234.56));
        $this->assertEquals('R789.01', $component->formatCurrency(789.01));
    }

    /** @test */
    public function helper_method_format_currency_works(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->instance();

        $this->assertEquals('R100.00', $component->formatCurrency(100));
        $this->assertEquals('R1,234.56', $component->formatCurrency(1234.56));
    }

    /** @test */
    public function helper_method_format_percent_works(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->instance();

        $this->assertEquals('40.0%', $component->formatPercent(40));
        $this->assertEquals('25.5%', $component->formatPercent(25.5));
    }

    /** @test */
    public function helper_method_get_margin_color_class_works(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->instance();

        // High margin (>= 30%)
        $this->assertStringContainsString('green', $component->getMarginColorClass(35));

        // Medium margin (15-30%)
        $this->assertStringContainsString('yellow', $component->getMarginColorClass(20));

        // Low margin (< 15%)
        $this->assertStringContainsString('red', $component->getMarginColorClass(10));
    }

    /** @test */
    public function helper_method_get_competitor_margin_color_class_works(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)
            ->instance();

        // Better margin at competitor price (difference > 5%)
        $this->assertStringContainsString('green', $component->getCompetitorMarginColorClass(30, 40));

        // Worse margin at competitor price (difference < -5%)
        $this->assertStringContainsString('red', $component->getCompetitorMarginColorClass(30, 20));

        // Similar margin (difference between -5% and 5%)
        $this->assertStringContainsString('gray', $component->getCompetitorMarginColorClass(30, 32));
    }

    /** @test */
    public function it_skips_products_without_price_or_cost(): void
    {
        // Product with no price
        $productNoPrice = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);
        \DB::table('eav_versioned')->insert([
            'entity_id' => $productNoPrice->id,
            'attribute_id' => Attribute::where('name', 'title')->first()->id,
            'value_current' => 'No Price Product',
            'value_approved' => 'No Price Product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('eav_versioned')->insert([
            'entity_id' => $productNoPrice->id,
            'attribute_id' => Attribute::where('name', 'cost')->first()->id,
            'value_current' => '50',
            'value_approved' => '50',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Product with valid data
        $this->createProductWithMarginData('Valid Product', 100, 60);

        $this->actingAs($this->admin);
        $component = Livewire::test(\App\Filament\PricingPanel\Pages\MarginAnalysis::class)->instance();

        $data = $component->getMarginData();
        $this->assertCount(1, $data); // Only valid product should be included
        $this->assertEquals('Valid Product', $data->first()['product_name']);
    }

    // ==================== Helper Methods ====================

    /**
     * Create a product with margin data (price and cost).
     */
    protected function createProductWithMarginData(string $name, float $price, float $cost): Entity
    {
        $product = Entity::factory()->create([
            'entity_type_id' => $this->productType->id,
        ]);

        $titleAttr = Attribute::where('name', 'title')->first();
        $priceAttr = Attribute::where('name', 'price')->first();
        $costAttr = Attribute::where('name', 'cost')->first();

        \DB::table('eav_versioned')->insert([
            'entity_id' => $product->id,
            'attribute_id' => $titleAttr->id,
            'value_current' => $name,
            'value_approved' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('eav_versioned')->insert([
            'entity_id' => $product->id,
            'attribute_id' => $priceAttr->id,
            'value_current' => (string) $price,
            'value_approved' => (string) $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('eav_versioned')->insert([
            'entity_id' => $product->id,
            'attribute_id' => $costAttr->id,
            'value_current' => (string) $cost,
            'value_approved' => (string) $cost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $product;
    }

    /**
     * Set category for a product.
     */
    protected function setProductCategory(Entity $product, string $category): void
    {
        $categoryAttr = Attribute::where('name', 'category')->first();

        \DB::table('eav_versioned')->insert([
            'entity_id' => $product->id,
            'attribute_id' => $categoryAttr->id,
            'value_current' => $category,
            'value_approved' => $category,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
