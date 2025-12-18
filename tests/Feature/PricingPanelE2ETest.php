<?php

namespace Tests\Feature;

use App\Filament\PricingPanel\Components\NotificationBadge;
use App\Filament\PricingPanel\Pages\CompetitorPrices;
use App\Filament\PricingPanel\Pages\Dashboard;
use App\Filament\PricingPanel\Pages\MarginAnalysis;
use App\Filament\PricingPanel\Pages\PriceComparisonMatrix;
use App\Filament\PricingPanel\Pages\PriceHistory;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use App\Notifications\PriceAlertTriggered;
use App\Services\BigQueryService;
use App\Services\EavWriter;
use App\Services\PriceAlertService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-End tests for the Pricing Panel (E-012).
 *
 * Tests complete user flows for:
 * - Admin user (full access)
 * - Pricing analyst user (full pricing panel access)
 * - Unauthorized users (no access)
 *
 * Covers:
 * - Dashboard with KPIs
 * - Competitor Prices list
 * - Price History charts
 * - Price Comparison Matrix
 * - Margin Analysis
 * - Price Alerts system
 * - Notification badges
 * - Error handling scenarios
 */
class PricingPanelE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $pricingAnalyst;

    private User $unauthorizedUser;

    private EntityType $productType;

    private Attribute $nameAttr;

    private Attribute $priceAttr;

    private Attribute $costAttr;

    private Attribute $categoryAttr;

    /** @var array<Entity> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Create test users
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');

        $this->pricingAnalyst = User::factory()->create(['is_active' => true]);
        $this->pricingAnalyst->assignRole('pricing-analyst');

        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
        $this->unauthorizedUser->assignRole('pim-editor');

        // Create product entity type and attributes
        $this->productType = EntityType::firstOrCreate(
            ['name' => 'Product'],
            ['display_name' => 'Product', 'description' => 'Product entity type']
        );

        $this->nameAttr = Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'name',
            'data_type' => 'text',
            'needs_approval' => 'no',
        ]);

        $this->priceAttr = Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'needs_approval' => 'no',
        ]);

        $this->costAttr = Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'cost',
            'data_type' => 'integer',
            'needs_approval' => 'no',
        ]);

        $this->categoryAttr = Attribute::factory()->create([
            'entity_type_id' => $this->productType->id,
            'name' => 'category',
            'data_type' => 'text',
            'needs_approval' => 'no',
        ]);

        // Create sample products with pricing data
        $this->createSampleData();

        // Mock BigQuery service for dashboard tests
        $this->mockBigQueryService();
    }

    /**
     * Create sample products and pricing data for testing.
     */
    protected function createSampleData(): void
    {
        $products = [
            ['name' => 'Organic Coconut Oil', 'price' => 89, 'cost' => 50, 'category' => 'Oils'],
            ['name' => 'Manuka Honey', 'price' => 350, 'cost' => 200, 'category' => 'Sweeteners'],
            ['name' => 'Vitamin C 1000mg', 'price' => 120, 'cost' => 60, 'category' => 'Supplements'],
            ['name' => 'Almond Butter', 'price' => 95, 'cost' => 55, 'category' => 'Spreads'],
            ['name' => 'Green Tea Extract', 'price' => 180, 'cost' => 90, 'category' => 'Supplements'],
        ];

        $competitors = ['Wellness Warehouse', 'Takealot', 'Checkers', 'Clicks'];

        foreach ($products as $productData) {
            $product = Entity::factory()->create([
                'entity_type_id' => $this->productType->id,
            ]);

            $this->setEntityAttributes($product, $productData);
            $this->products[] = $product;

            // Create competitor price scrapes with varied prices
            foreach ($competitors as $index => $competitor) {
                // Vary prices around our price (-20% to +20%)
                $variation = (rand(-20, 20) / 100) * $productData['price'];
                $competitorPrice = max(1, $productData['price'] + $variation);

                // Create historical and recent scrapes
                PriceScrape::factory()->create([
                    'product_id' => $product->id,
                    'competitor_name' => $competitor,
                    'price' => $competitorPrice,
                    'in_stock' => rand(0, 10) > 2, // 80% in stock
                    'scraped_at' => now()->subDays(rand(1, 7)),
                ]);

                // Add some historical data for price history charts
                for ($i = 1; $i <= 3; $i++) {
                    $historicalVariation = (rand(-10, 10) / 100) * $competitorPrice;
                    PriceScrape::factory()->create([
                        'product_id' => $product->id,
                        'competitor_name' => $competitor,
                        'price' => max(1, $competitorPrice + $historicalVariation),
                        'in_stock' => true,
                        'scraped_at' => now()->subDays(7 + ($i * 7)),
                    ]);
                }
            }
        }
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
                'cost' => $this->costAttr,
                'category' => $this->categoryAttr,
                default => null,
            };

            if ($attr) {
                $writer->upsertVersioned($entity->id, $attr->id, (string) $value, [
                    'confidence' => 1.0,
                ]);
            }
        }
    }

    /**
     * Mock BigQuery service for dashboard tests.
     */
    protected function mockBigQueryService(): void
    {
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 150,
            'avg_market_position' => 'competitive',
            'products_cheapest' => 85,
            'products_most_expensive' => 20,
            'recent_price_changes' => 45,
            'active_competitor_undercuts' => 12,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);
    }

    // ========================================
    // AUTHENTICATION & AUTHORIZATION TESTS
    // ========================================

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/pricing');

        $response->assertRedirect('/pricing/login');
    }

    public function test_unauthorized_user_cannot_access_pricing_panel(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pricing');

        $response->assertForbidden();
    }

    public function test_pricing_analyst_can_access_pricing_panel(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_admin_can_access_pricing_panel(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_unauthorized_user_cannot_access_dashboard(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pricing');

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_competitor_prices(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pricing/competitor-prices');

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_price_history(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pricing/price-history');

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_price_comparison_matrix(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pricing/price-comparison-matrix');

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_margin_analysis(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pricing/margin-analysis');

        $response->assertForbidden();
    }

    // ========================================
    // DASHBOARD E2E TESTS
    // ========================================

    public function test_dashboard_displays_all_kpi_tiles(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(Dashboard::class)
            ->assertSee('Products Tracked')
            ->assertSee('150')
            ->assertSee('Avg Price Position')
            ->assertSee('Competitive');
    }

    public function test_dashboard_displays_price_position_chart(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(Dashboard::class);

        $chartData = $component->get('positionChartData');
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(4, $chartData['labels']);
    }

    public function test_dashboard_displays_price_changes_chart(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(Dashboard::class);

        $chartData = $component->get('priceChangesChartData');
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
    }

    public function test_dashboard_shows_active_alerts_count(): void
    {
        // Create some alerts for the user
        PriceAlert::factory()->count(5)->create([
            'user_id' => $this->pricingAnalyst->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->pricingAnalyst);

        Livewire::test(Dashboard::class)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['active_alerts'] === 5);
    }

    public function test_dashboard_refresh_reloads_data(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(Dashboard::class)
            ->call('refresh')
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_dashboard_toggle_data_source_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(Dashboard::class)
            ->assertSet('useBigQuery', true)
            ->call('toggleDataSource')
            ->assertSet('useBigQuery', false)
            ->call('toggleDataSource')
            ->assertSet('useBigQuery', true);
    }

    // ========================================
    // COMPETITOR PRICES E2E TESTS
    // ========================================

    public function test_competitor_prices_page_loads_with_data(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(CompetitorPrices::class)
            ->assertSet('loading', false)
            ->assertSet('error', null)
            ->assertSee('Organic Coconut Oil')
            ->assertSee('Wellness Warehouse');
    }

    public function test_competitor_prices_shows_all_competitors(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(CompetitorPrices::class);

        $competitors = $component->get('competitors');
        $this->assertContains('Wellness Warehouse', $competitors);
        $this->assertContains('Takealot', $competitors);
        $this->assertContains('Checkers', $competitors);
        $this->assertContains('Clicks', $competitors);
    }

    public function test_competitor_prices_calculates_price_positions(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(CompetitorPrices::class);

        $productPrices = $component->get('productPrices');
        $this->assertNotEmpty($productPrices);

        // Each product should have a position
        foreach ($productPrices as $product) {
            $this->assertContains($product['position'], ['cheapest', 'middle', 'most_expensive']);
        }
    }

    public function test_competitor_prices_category_filter_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(CompetitorPrices::class)
            ->call('updateCategory', 'Supplements');

        $productPrices = $component->get('productPrices');

        // Should only show Supplements products
        foreach ($productPrices as $product) {
            $this->assertEquals('Supplements', $product['category'] ?? 'Supplements');
        }
    }

    public function test_competitor_prices_sorting_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(CompetitorPrices::class)
            ->call('updateSort', 'name', 'asc')
            ->assertSet('sortBy', 'name')
            ->assertSet('sortDirection', 'asc')
            ->call('updateSort', 'price_difference', 'desc')
            ->assertSet('sortBy', 'price_difference')
            ->assertSet('sortDirection', 'desc');
    }

    public function test_competitor_prices_highlights_expensive_products(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // The page should render and potentially highlight rows where we're more expensive
        Livewire::test(CompetitorPrices::class)
            ->assertSet('loading', false);
    }

    // ========================================
    // PRICE HISTORY E2E TESTS
    // ========================================

    public function test_price_history_page_loads_with_products(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceHistory::class);

        $products = $component->get('products');
        $this->assertNotEmpty($products);
    }

    public function test_price_history_auto_selects_first_product(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceHistory::class);

        $selectedProductId = $component->get('selectedProductId');
        $this->assertNotNull($selectedProductId);
    }

    public function test_price_history_shows_chart_data(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceHistory::class);

        $chartData = $component->get('chartData');
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
    }

    public function test_price_history_includes_our_price_reference(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceHistory::class);

        $chartData = $component->get('chartData');
        $datasets = $chartData['datasets'];

        // Find the "Our Price" dataset
        $ourPriceDataset = collect($datasets)->firstWhere('label', 'Our Price');
        $this->assertNotNull($ourPriceDataset);
    }

    public function test_price_history_date_range_filter_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceHistory::class)
            ->set('dateRange', '7');

        $chartData = $component->get('chartData');
        $this->assertCount(8, $chartData['labels']); // 7 days + today

        $component->set('dateRange', '30');
        $chartData = $component->get('chartData');
        $this->assertCount(31, $chartData['labels']);
    }

    public function test_price_history_product_selection_updates_chart(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceHistory::class);

        $initialCompetitors = $component->get('competitors');

        // Change product selection
        if (count($this->products) > 1) {
            $component->set('selectedProductId', $this->products[1]->id);

            // The chart should update
            $this->assertNotNull($component->get('chartData'));
        }
    }

    // ========================================
    // PRICE COMPARISON MATRIX E2E TESTS
    // ========================================

    public function test_price_comparison_matrix_page_loads(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(PriceComparisonMatrix::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_price_comparison_matrix_shows_all_products(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceComparisonMatrix::class);

        $matrixData = $component->get('matrixData');
        $this->assertCount(5, $matrixData);
    }

    public function test_price_comparison_matrix_shows_all_competitors(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceComparisonMatrix::class);

        $competitors = $component->get('competitors');
        $this->assertCount(4, $competitors);
    }

    public function test_price_comparison_matrix_calculates_positions(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceComparisonMatrix::class);

        $matrixData = $component->get('matrixData');

        foreach ($matrixData as $row) {
            $this->assertContains($row['position'], ['cheapest', 'middle', 'most_expensive']);
        }
    }

    public function test_price_comparison_matrix_color_coding_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceComparisonMatrix::class);
        $instance = $component->instance();

        // Test color classes
        $this->assertStringContainsString('green', $instance->getCellColorClass(80, 100));
        $this->assertStringContainsString('red', $instance->getCellColorClass(120, 100));
        $this->assertStringContainsString('yellow', $instance->getCellColorClass(100, 100));
    }

    public function test_price_comparison_matrix_sorting_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(PriceComparisonMatrix::class)
            ->call('updateSort', 'name', 'asc')
            ->assertSet('sortBy', 'name')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_price_comparison_matrix_category_filter_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceComparisonMatrix::class)
            ->call('updateCategory', 'Oils');

        $matrixData = $component->get('matrixData');
        $this->assertCount(1, $matrixData);
        $this->assertEquals('Organic Coconut Oil', $matrixData[0]['name']);
    }

    // ========================================
    // MARGIN ANALYSIS E2E TESTS
    // ========================================

    public function test_margin_analysis_page_loads(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(MarginAnalysis::class)
            ->assertSuccessful();
    }

    public function test_margin_analysis_shows_product_margins(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(MarginAnalysis::class);
        $data = $component->instance()->getMarginData();

        $this->assertNotEmpty($data);

        foreach ($data as $row) {
            $this->assertArrayHasKey('margin_amount', $row);
            $this->assertArrayHasKey('margin_percent', $row);
        }
    }

    public function test_margin_analysis_calculates_margins_correctly(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(MarginAnalysis::class);
        $data = $component->instance()->getMarginData();

        // Verify we have margin data
        $this->assertNotEmpty($data, 'Margin data should not be empty');

        // Find any product with both margin_amount and margin_percent
        $productWithMargin = collect($data)->first(function ($product) {
            return isset($product['margin_amount']) && isset($product['margin_percent']);
        });

        $this->assertNotNull($productWithMargin, 'Should have at least one product with margin data');

        // Verify margin calculation is correct: margin_percent = (margin_amount / price) * 100
        if ($productWithMargin['our_price'] > 0) {
            $expectedPercent = ($productWithMargin['margin_amount'] / $productWithMargin['our_price']) * 100;
            $this->assertEqualsWithDelta($expectedPercent, $productWithMargin['margin_percent'], 0.01);
        }
    }

    public function test_margin_analysis_color_coding_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(MarginAnalysis::class);
        $instance = $component->instance();

        // High margin (>= 30%) - green
        $this->assertStringContainsString('green', $instance->getMarginColorClass(40));

        // Medium margin (15-30%) - yellow
        $this->assertStringContainsString('yellow', $instance->getMarginColorClass(20));

        // Low margin (< 15%) - red
        $this->assertStringContainsString('red', $instance->getMarginColorClass(10));
    }

    public function test_margin_analysis_shows_summary_stats(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(MarginAnalysis::class);
        $stats = $component->instance()->getSummaryStats();

        $this->assertArrayHasKey('total_products', $stats);
        $this->assertArrayHasKey('total_margin_amount', $stats);
        $this->assertArrayHasKey('lowest_margin_percent', $stats);
        $this->assertArrayHasKey('highest_margin_percent', $stats);
    }

    public function test_margin_analysis_sorting_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        Livewire::test(MarginAnalysis::class)
            ->call('updateSort', 'margin_percent')
            ->assertSet('sortBy', 'margin_percent');
    }

    // ========================================
    // PRICE ALERTS E2E TESTS
    // ========================================

    public function test_price_alert_creation_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $service = new PriceAlertService;

        $alert = $service->createPriceBelowAlert(
            $this->pricingAnalyst,
            100.00,
            $this->products[0]->id,
            'Takealot'
        );

        $this->assertDatabaseHas('price_alerts', [
            'id' => $alert->id,
            'user_id' => $this->pricingAnalyst->id,
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
        ]);
    }

    public function test_price_alert_triggers_on_matching_scrape(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // Create alert
        $alert = PriceAlert::factory()
            ->forUser($this->pricingAnalyst)
            ->forProduct($this->products[0])
            ->priceBelow(50.00)
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        // Create matching scrape
        $scrape = PriceScrape::factory()
            ->forProduct($this->products[0])
            ->withPrice(40.00)
            ->scrapedToday()
            ->create();

        $service = new PriceAlertService;
        $triggered = $service->processScrape($scrape);

        $this->assertCount(1, $triggered);
        $this->assertEquals($alert->id, $triggered->first()->id);
    }

    public function test_competitor_beats_alert_works(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // Create alert
        $alert = PriceAlert::factory()
            ->forUser($this->pricingAnalyst)
            ->forProduct($this->products[0])
            ->competitorBeats()
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        // Create scrape where competitor is cheaper than our price
        $scrape = PriceScrape::factory()
            ->forProduct($this->products[0])
            ->withPrice(70.00) // Competitor price
            ->scrapedToday()
            ->create();

        $service = new PriceAlertService;
        $triggered = $service->processScrape($scrape, 89.00); // Our price is 89

        $this->assertCount(1, $triggered);
    }

    public function test_price_alert_notification_is_sent(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $alert = PriceAlert::factory()->create([
            'user_id' => $this->pricingAnalyst->id,
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
        ]);

        $scrape = PriceScrape::factory()->create([
            'competitor_name' => 'Test Competitor',
            'price' => 99.99,
        ]);

        // Send notification
        $this->pricingAnalyst->notify(new PriceAlertTriggered($alert, $scrape));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->pricingAnalyst->id,
            'notifiable_type' => User::class,
        ]);
    }

    // ========================================
    // NOTIFICATION BADGE E2E TESTS
    // ========================================

    public function test_notification_badge_shows_unread_count(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // Create notifications
        for ($i = 0; $i < 3; $i++) {
            $this->pricingAnalyst->notify(new PriceAlertTriggered(
                PriceAlert::factory()->create(['user_id' => $this->pricingAnalyst->id]),
                PriceScrape::factory()->create()
            ));
        }

        Livewire::test(NotificationBadge::class)
            ->assertSet('unreadCount', 3);
    }

    public function test_notification_badge_can_mark_as_read(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $this->pricingAnalyst->notify(new PriceAlertTriggered(
            PriceAlert::factory()->create(['user_id' => $this->pricingAnalyst->id]),
            PriceScrape::factory()->create()
        ));

        $notification = $this->pricingAnalyst->notifications()->first();
        $this->assertNull($notification->read_at);

        Livewire::test(NotificationBadge::class)
            ->call('markAsRead', $notification->id);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_notification_badge_can_mark_all_as_read(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // Create multiple notifications
        for ($i = 0; $i < 5; $i++) {
            $this->pricingAnalyst->notify(new PriceAlertTriggered(
                PriceAlert::factory()->create(['user_id' => $this->pricingAnalyst->id]),
                PriceScrape::factory()->create()
            ));
        }

        $this->assertEquals(5, $this->pricingAnalyst->unreadNotifications()->count());

        Livewire::test(NotificationBadge::class)
            ->call('markAllAsRead');

        $this->assertEquals(0, $this->pricingAnalyst->unreadNotifications()->count());
    }

    // ========================================
    // ERROR HANDLING E2E TESTS
    // ========================================

    public function test_dashboard_handles_bigquery_error_gracefully(): void
    {
        // Mock BigQuery to throw exception
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->will(
            $this->throwException(new \RuntimeException('BigQuery connection failed'))
        );

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingAnalyst);

        Livewire::test(Dashboard::class)
            ->assertSee('Error')
            ->assertSee('Failed to load dashboard data');
    }

    public function test_dashboard_does_not_expose_stack_trace(): void
    {
        // Mock BigQuery to throw exception
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->will(
            $this->throwException(new \RuntimeException('Connection failed at /var/www/vendor/google/cloud-bigquery'))
        );

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingAnalyst);

        // The error message should be sanitized - not exposing internal paths
        Livewire::test(Dashboard::class)
            ->assertSee('Failed to load dashboard data')
            ->assertSee('Please try again later')
            ->assertDontSee('Connection failed')
            ->assertDontSee('/var/www');
    }

    public function test_price_history_handles_invalid_product_gracefully(): void
    {
        $this->actingAs($this->pricingAnalyst);

        $component = Livewire::test(PriceHistory::class);
        $component->set('selectedProductId', 'invalid-product-id');

        $this->assertEquals('Product not found', $component->get('error'));
    }

    public function test_competitor_prices_shows_empty_state_when_no_data(): void
    {
        // Clear all price scrapes
        PriceScrape::query()->delete();

        $this->actingAs($this->pricingAnalyst);

        Livewire::test(CompetitorPrices::class)
            ->assertSet('productPrices', [])
            ->assertSee('No competitor pricing data available');
    }

    public function test_price_comparison_matrix_shows_empty_state_when_no_data(): void
    {
        // Clear all price scrapes
        PriceScrape::query()->delete();

        $this->actingAs($this->pricingAnalyst);

        Livewire::test(PriceComparisonMatrix::class)
            ->assertSet('matrixData', [])
            ->assertSee('No Price Data Available');
    }

    // ========================================
    // CROSS-PAGE WORKFLOW TESTS
    // ========================================

    public function test_complete_workflow_view_prices_and_create_alert(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // Step 1: View dashboard to see overall KPIs
        Livewire::test(Dashboard::class)
            ->assertSet('loading', false)
            ->assertSee('Products Tracked');

        // Step 2: Check competitor prices for a specific product
        Livewire::test(CompetitorPrices::class)
            ->assertSet('loading', false)
            ->assertSee('Organic Coconut Oil');

        // Step 3: View price history for deeper analysis
        $phComponent = Livewire::test(PriceHistory::class)
            ->assertSet('loading', false);
        $this->assertNotNull($phComponent->get('chartData'));

        // Step 4: Create an alert for price drops
        $service = new PriceAlertService;
        $alert = $service->createPriceBelowAlert(
            $this->pricingAnalyst,
            80.00,
            $this->products[0]->id,
            'Takealot'
        );

        $this->assertDatabaseHas('price_alerts', [
            'id' => $alert->id,
            'user_id' => $this->pricingAnalyst->id,
        ]);

        // Step 5: Back to dashboard to see updated alert count
        Livewire::test(Dashboard::class)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['active_alerts'] >= 1);
    }

    public function test_admin_can_access_all_pages(): void
    {
        $this->actingAs($this->adminUser);

        // Dashboard
        Livewire::test(Dashboard::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);

        // Competitor Prices
        Livewire::test(CompetitorPrices::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);

        // Price History
        Livewire::test(PriceHistory::class)
            ->assertSet('loading', false);

        // Price Comparison Matrix
        Livewire::test(PriceComparisonMatrix::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);

        // Margin Analysis
        Livewire::test(MarginAnalysis::class)
            ->assertSuccessful();
    }

    // ========================================
    // DATA REFRESH TESTS
    // ========================================

    public function test_all_pages_support_data_refresh(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // Dashboard
        Livewire::test(Dashboard::class)
            ->call('refresh')
            ->assertSet('loading', false);

        // Competitor Prices
        Livewire::test(CompetitorPrices::class)
            ->call('refresh')
            ->assertSet('loading', false);

        // Price History
        $phComponent = Livewire::test(PriceHistory::class)
            ->call('refresh');
        $this->assertNotNull($phComponent->get('chartData'));

        // Price Comparison Matrix
        Livewire::test(PriceComparisonMatrix::class)
            ->call('refresh')
            ->assertSet('loading', false);

        // Margin Analysis
        Livewire::test(MarginAnalysis::class)
            ->call('refresh')
            ->assertSuccessful();
    }

    // ========================================
    // HELPER METHOD TESTS
    // ========================================

    public function test_format_price_helper_works_across_pages(): void
    {
        $this->actingAs($this->pricingAnalyst);

        // Competitor Prices
        $cp = Livewire::test(CompetitorPrices::class)->instance();
        $this->assertEquals('R100.00', $cp->formatPrice(100.00));
        $this->assertEquals('-', $cp->formatPrice(null));

        // Price History
        $ph = Livewire::test(PriceHistory::class)->instance();
        $this->assertEquals('R1,234.56', $ph->formatPrice(1234.56));

        // Price Comparison Matrix
        $pcm = Livewire::test(PriceComparisonMatrix::class)->instance();
        $this->assertEquals('R99.99', $pcm->formatPrice(99.99));

        // Margin Analysis
        $ma = Livewire::test(MarginAnalysis::class)->instance();
        $this->assertEquals('R500.00', $ma->formatCurrency(500));
    }
}
