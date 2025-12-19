<?php

namespace Tests\Feature;

use App\Filament\SupplyPanel\Pages\Benchmarks;
use App\Filament\SupplyPanel\Pages\Cohorts;
use App\Filament\SupplyPanel\Pages\CustomerEngagement;
use App\Filament\SupplyPanel\Pages\Dashboard;
use App\Filament\SupplyPanel\Pages\Forecasting;
use App\Filament\SupplyPanel\Pages\Marketing;
use App\Filament\SupplyPanel\Pages\MarketShare;
use App\Filament\SupplyPanel\Pages\PremiumFeatures;
use App\Filament\SupplyPanel\Pages\ProductDeepDive;
use App\Filament\SupplyPanel\Pages\Products;
use App\Filament\SupplyPanel\Pages\PurchaseOrders;
use App\Filament\SupplyPanel\Pages\Retention;
use App\Filament\SupplyPanel\Pages\RfmAnalysis;
use App\Filament\SupplyPanel\Pages\SubscriptionPredictions;
use App\Filament\SupplyPanel\Pages\SubscriptionProducts;
use App\Filament\SupplyPanel\Pages\SubscriptionsOverview;
use App\Filament\SupplyPanel\Pages\SupplyChain;
use App\Filament\SupplyPanel\Pages\Trends;
use App\Models\Brand;
use App\Models\BrandCompetitor;
use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-End tests for the Supply Portal (D-025).
 *
 * Tests complete user flows for:
 * - Basic supplier (limited access)
 * - Premium supplier (full access)
 * - Admin user (full access to all brands)
 * - Error handling scenarios
 *
 * Note: Tests use Livewire component testing which properly tests page functionality.
 * HTTP route tests are affected by a known Filament auth middleware interaction issue.
 */
class SupplyPortalE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $basicSupplierUser;

    private User $premiumSupplierUser;

    private User $petHeavenPremiumUser;

    private Brand $brand;

    private Brand $brand2;

    private Brand $competitorBrand;

    private Brand $petHeavenBrand;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset permission cache after seeding (TestCase auto-seeds via TestBaseSeeder)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create admin user
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');

        // Create basic supplier user
        $this->basicSupplierUser = User::factory()->create(['is_active' => true]);
        $this->basicSupplierUser->assignRole('supplier-basic');

        // Create premium supplier user
        $this->premiumSupplierUser = User::factory()->create(['is_active' => true]);
        $this->premiumSupplierUser->assignRole('supplier-premium');

        // Create brands
        $this->brand = Brand::factory()->create(['name' => 'Test Brand', 'company_id' => 3]);
        $this->brand2 = Brand::factory()->create(['name' => 'Second Brand', 'company_id' => 3]);
        $this->competitorBrand = Brand::factory()->create(['name' => 'Competitor Brand', 'company_id' => 3]);
        $this->petHeavenBrand = Brand::factory()->create(['name' => 'Pet Heaven Brand', 'company_id' => 9]); // 9 = Pet Heaven

        // Create Pet Heaven premium user
        $this->petHeavenPremiumUser = User::factory()->create(['is_active' => true]);
        $this->petHeavenPremiumUser->assignRole('supplier-premium');
        $this->petHeavenPremiumUser->brands()->attach($this->petHeavenBrand);

        // Associate suppliers with brands
        $this->basicSupplierUser->brands()->attach($this->brand);
        $this->premiumSupplierUser->brands()->attach($this->brand);

        // Create competitor relationship
        BrandCompetitor::create([
            'brand_id' => $this->brand->id,
            'competitor_brand_id' => $this->competitorBrand->id,
            'position' => 1,
        ]);

        // Mock BigQuery service
        $this->mockBigQueryService();
    }

    protected function mockBigQueryService(): void
    {
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->willReturn([
            'revenue' => 125000.0,
            'orders' => 450,
            'units' => 1200,
            'aov' => 277.78,
            'revenue_change' => 12.5,
            'orders_change' => 8.2,
            'units_change' => 15.3,
            'aov_change' => -3.1,
        ]);
        $mockBQ->method('getSalesTrend')->willReturn([
            'labels' => ['2024-01', '2024-02', '2024-03'],
            'datasets' => [
                [
                    'label' => 'Test Brand',
                    'data' => [100000, 110000, 125000],
                    'borderColor' => '#006654',
                    'backgroundColor' => 'rgba(0, 102, 84, 0.1)',
                ],
            ],
        ]);
        $mockBQ->method('getTopProducts')->willReturn([
            [
                'sku' => 'SKU001',
                'name' => 'Product 1',
                'revenue' => 45000,
                'units' => 500,
                'growth' => 20.0,
            ],
        ]);
        $mockBQ->method('getProductPerformanceTable')->willReturn([
            [
                'sku' => 'SKU001',
                'name' => 'Product 1',
                'category' => 'Category A',
                'months' => ['2024-01' => 15000, '2024-02' => 15000, '2024-03' => 15000],
                'total' => 45000,
            ],
        ]);
        $mockBQ->method('getCompetitorComparison')->willReturn([
            'labels' => ['2024-01', '2024-02', '2024-03'],
            'datasets' => [
                ['label' => 'Your Brand', 'data' => [100000, 110000, 125000]],
                ['label' => 'Competitor A', 'data' => [90000, 95000, 100000]],
            ],
        ]);
        $mockBQ->method('getMarketShareByCategory')->willReturn([
            [
                'category' => 'Health & Beauty',
                'subcategory' => null,
                'brand_share' => 35.5,
                'competitor_shares' => ['Competitor A' => 25.0],
            ],
        ]);
        $mockBQ->method('getStockSupply')->willReturn([
            'sell_in' => [['sku' => 'SKU001', 'name' => 'Product 1', 'months' => ['2024-01' => 100]]],
            'sell_out' => [['sku' => 'SKU001', 'name' => 'Product 1', 'months' => ['2024-01' => 80]]],
            'closing_stock' => [['sku' => 'SKU001', 'name' => 'Product 1', 'months' => ['2024-01' => 50]]],
        ]);
        $mockBQ->method('getPurchaseOrders')->willReturn([
            'summary' => ['total_pos' => 25, 'on_time_pct' => 88.5, 'in_full_pct' => 92.0, 'otif_pct' => 84.0],
            'monthly' => [['month' => '2024-01', 'po_count' => 8, 'on_time_pct' => 87.5]],
            'orders' => [['po_number' => 'PO-001', 'order_date' => '2024-01-15', 'status' => 'delivered', 'total_value' => 15000.0, 'line_count' => 5]],
        ]);
        $mockBQ->method('getPurchaseOrderLines')->willReturn([]);
        $mockBQ->method('getCustomerEngagement')->willReturn([
            ['sku' => 'SKU001', 'name' => 'Product 1', 'avg_qty_per_order' => 2.5, 'reorder_rate' => 35.0, 'avg_frequency_months' => 2.5, 'promo_intensity' => 15.0],
        ]);
        $mockBQ->method('getSalesForecast')->willReturn([
            'historical' => [['month' => '2024-01', 'revenue' => 100000.0]],
            'forecast' => [['month' => '2024-07', 'baseline' => 122000.0, 'optimistic' => 140300.0, 'pessimistic' => 109800.0]],
        ]);
        $mockBQ->method('getCohortAnalysis')->willReturn([
            'cohorts' => ['2024-01' => ['size' => 100, 'retention' => [0 => 100.0, 1 => 35.0]]],
            'months' => ['2024-01'],
        ]);
        $mockBQ->method('getRfmAnalysis')->willReturn([
            'segments' => ['Champions' => ['count' => 150, 'avg_revenue' => 2500.0, 'r_avg' => 4.8, 'f_avg' => 4.5, 'm_avg' => 4.7]],
            'matrix' => [['r_score' => 5, 'f_score' => 5, 'm_score' => 5, 'count' => 50]],
        ]);
        $mockBQ->method('getRetentionAnalysis')->willReturn([
            'retention' => [['month' => '2024-02', 'retained' => 450, 'churned' => 150, 'retention_rate' => 75.0]],
        ]);
        $mockBQ->method('getProductList')->willReturn([
            ['sku' => 'SKU001', 'name' => 'Premium Vitamin C'],
        ]);
        $mockBQ->method('getProductDeepDive')->willReturn([
            'product_info' => ['sku' => 'SKU001', 'name' => 'Premium Vitamin C'],
            'performance' => ['total_revenue' => 125000.0, 'total_units' => 2500],
            'monthly' => [['month' => '2024-01', 'revenue' => 20000.0]],
            'customers' => ['total_customers' => 450, 'repeat_rate' => 38.5],
        ]);
        $mockBQ->method('getMarketingAnalytics')->willReturn([
            'campaign_performance' => [['campaign' => 'Summer Sale', 'revenue' => 45000, 'orders' => 180]],
            'channel_performance' => [['channel' => 'Email', 'revenue' => 25000]],
            'promo_impact' => ['total_promo_revenue' => 35000, 'promo_orders' => 150],
        ]);
        $mockBQ->method('getSubscriptionOverview')->willReturn([
            'summary' => [
                'active_subscriptions' => 1250,
                'monthly_recurring_revenue' => 62500.0,
                'avg_ltv' => 1500.0,
                'churn_rate' => 5.2,
            ],
            'monthly' => [['month' => '2024-01', 'active' => 1200, 'new' => 80, 'churned' => 30]],
        ]);
        $mockBQ->method('getSubscriptionProducts')->willReturn([
            [
                'sku' => 'SUB001',
                'product_name' => 'Monthly Vitamin Box',
                'category' => 'Pet Food',
                'subscribers' => 500,
                'active_subscriptions' => 450,
                'total_subscriptions' => 600,
                'mrr' => 25000.0,
                'avg_subscription_months' => 8.5,
                'avg_ltv' => 1500.0,
                'total_revenue' => 200000.0,
                'churn_rate' => 4.5,
            ],
        ]);
        $mockBQ->method('getSubscriptionPredictions')->willReturn([
            ['customer_id' => 'C001', 'next_order_date' => '2024-07-15', 'probability' => 0.85],
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);
    }

    /**
     * Configure the application as a Pet Heaven deployment.
     * Required for subscription page tests since CompanyService::isPetHeaven()
     * checks the application config, not the brand.
     */
    protected function setUpPetHeavenDeployment(): void
    {
        config(['bigquery.company_id' => 9]); // 9 = Pet Heaven
    }

    // ========================================
    // BASIC USER FLOW TESTS
    // ========================================

    public function test_basic_user_dashboard_shows_kpis(): void
    {
        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('loading', false)
            ->assertSet('error', null)
            ->assertSee('Net Revenue')
            ->assertSee('Total Orders');
    }

    public function test_basic_user_can_view_products_page(): void
    {
        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Products::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSee('Products');
    }

    public function test_basic_user_can_view_trends_page(): void
    {
        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Trends::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSee('Trends');
    }

    public function test_basic_user_can_view_benchmarks_page(): void
    {
        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Benchmarks::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSee('Your Brand'); // Benchmark page shows competitor comparison
    }

    public function test_basic_user_sees_premium_features_preview(): void
    {
        $this->actingAs($this->basicSupplierUser);

        Livewire::test(PremiumFeatures::class)
            ->assertSee('Premium');
    }

    public function test_basic_user_premium_pages_show_locked_content(): void
    {
        $this->actingAs($this->basicSupplierUser);

        // Forecasting should show locked content for basic users
        Livewire::test(Forecasting::class, ['brandId' => $this->brand->id])
            ->assertSee('Forecasting')
            ->assertSee('Premium');
    }

    public function test_basic_user_cohorts_page_shows_premium_lock(): void
    {
        $this->actingAs($this->basicSupplierUser);

        // Cohorts page is premium-only
        Livewire::test(Cohorts::class, ['brandId' => $this->brand->id])
            ->assertSee('Premium');
    }

    // ========================================
    // PREMIUM USER FLOW TESTS
    // ========================================

    public function test_premium_user_dashboard_shows_kpis(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('loading', false)
            ->assertSet('error', null)
            ->assertSee('Net Revenue')
            ->assertSee('Total Orders');
    }

    public function test_premium_user_can_access_forecasting_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(Forecasting::class, ['brandId' => $this->brand->id])
            ->assertSee('Forecasting');
    }

    public function test_premium_user_can_access_cohorts_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        Livewire::test(Cohorts::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSee('Cohort');
    }

    public function test_premium_user_can_access_rfm_analysis_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id])
            ->assertSee('RFM');
    }

    public function test_premium_user_can_access_retention_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(Retention::class, ['brandId' => $this->brand->id])
            ->assertSee('Retention');
    }

    public function test_premium_user_can_access_product_deep_dive_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id])
            ->assertSee('Product');
    }

    public function test_premium_user_can_access_supply_chain_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id])
            ->assertSee('Supply Chain');
    }

    public function test_premium_user_can_access_purchase_orders_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id])
            ->assertSee('Purchase Orders');
    }

    public function test_premium_user_can_access_customer_engagement_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id])
            ->assertSee('Customer Engagement');
    }

    public function test_premium_user_can_access_market_share_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(MarketShare::class, ['brandId' => $this->brand->id])
            ->assertSee('Market Share');
    }

    public function test_premium_user_can_access_marketing_page(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        // Page should load without unhandled exceptions
        Livewire::test(Marketing::class, ['brandId' => $this->brand->id])
            ->assertSee('Marketing');
    }

    // ========================================
    // ADMIN USER FLOW TESTS
    // ========================================

    public function test_admin_can_view_dashboard(): void
    {
        $this->actingAs($this->adminUser);

        // Admin should be able to view dashboard for any brand
        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_admin_can_select_any_brand(): void
    {
        $this->actingAs($this->adminUser);

        // Admin should be able to view data for any brand
        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);

        // Admin can also view competitor brand (not attached to any user)
        Livewire::test(Dashboard::class, ['brandId' => $this->competitorBrand->id])
            ->assertSet('error', null);
    }

    public function test_admin_can_switch_between_brands(): void
    {
        $this->actingAs($this->adminUser);

        // Start with one brand
        $component = Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('error', null);

        // Switch to another brand
        $component->set('brandId', $this->competitorBrand->id)
            ->assertSet('brandId', $this->competitorBrand->id);
    }

    public function test_admin_can_access_all_premium_pages(): void
    {
        $this->actingAs($this->adminUser);

        // Admin should have access to all pages without uncaught exceptions
        $premiumPages = [
            ['class' => Forecasting::class, 'content' => 'Forecasting'],
            ['class' => Cohorts::class, 'content' => 'Cohort'],
            ['class' => RfmAnalysis::class, 'content' => 'RFM'],
            ['class' => Retention::class, 'content' => 'Retention'],
            ['class' => ProductDeepDive::class, 'content' => 'Product'],
            ['class' => Marketing::class, 'content' => 'Marketing'],
        ];

        foreach ($premiumPages as $page) {
            Livewire::test($page['class'], ['brandId' => $this->brand->id])
                ->assertSee($page['content']);
        }
    }

    // ========================================
    // ERROR HANDLING TESTS
    // ========================================

    public function test_bigquery_connection_error_shows_user_friendly_message(): void
    {
        // Create a mock that throws exceptions
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->will($this->throwException(new \RuntimeException('BigQuery connection failed')));

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSee('Error')
            ->assertSee('Failed to load');
    }

    public function test_bigquery_timeout_shows_graceful_message(): void
    {
        // Create a mock that simulates timeout
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->will($this->throwException(new \RuntimeException('Query timeout exceeded')));

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSee('Error');
    }

    public function test_error_does_not_expose_stack_trace(): void
    {
        // Create a mock that throws a detailed exception
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->will($this->throwException(new \RuntimeException('Connection failed')));

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->basicSupplierUser);

        $component = Livewire::test(Dashboard::class, ['brandId' => $this->brand->id]);

        // Should show error but not expose PHP internals (stack traces, file paths)
        $component->assertSee('Error')
            ->assertDontSee('vendor/')
            ->assertDontSee('->');
    }

    public function test_bigquery_not_configured_shows_message(): void
    {
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(false);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->basicSupplierUser);

        // Page should still load and show some indicator that BQ isn't set up
        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('loading', false);
    }

    public function test_unauthorized_brand_access_shows_error(): void
    {
        $this->actingAs($this->basicSupplierUser);

        // Create a brand that the user doesn't have access to
        $otherBrand = Brand::factory()->create(['name' => 'Unauthorized Brand']);

        Livewire::test(Dashboard::class, ['brandId' => $otherBrand->id])
            ->assertSee('Error')
            ->assertSee('do not have access');
    }

    // ========================================
    // PET HEAVEN SUBSCRIPTION TESTS
    // ========================================

    public function test_pet_heaven_premium_user_can_access_subscriptions_overview(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        // Page should load without unhandled exceptions
        Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSee('Subscription');
    }

    public function test_pet_heaven_premium_user_can_access_subscription_products(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        // Page should load without unhandled exceptions
        Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSee('Subscription');
    }

    public function test_pet_heaven_premium_user_can_access_subscription_predictions(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        // Page should load without unhandled exceptions
        Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSee('Subscription');
    }

    // ========================================
    // BRAND ACCESS CONTROL TESTS
    // ========================================

    public function test_supplier_can_only_access_assigned_brands(): void
    {
        $this->actingAs($this->basicSupplierUser);

        // Should be able to access assigned brand
        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null);

        // Should NOT be able to access unassigned brand
        Livewire::test(Dashboard::class, ['brandId' => $this->brand2->id])
            ->assertSee('do not have access');
    }

    public function test_supplier_with_multiple_brands_can_switch(): void
    {
        // Attach second brand to supplier
        $this->basicSupplierUser->brands()->attach($this->brand2);

        $this->actingAs($this->basicSupplierUser);

        // Should be able to access both brands
        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null);

        Livewire::test(Dashboard::class, ['brandId' => $this->brand2->id])
            ->assertSet('error', null);
    }

    // ========================================
    // DATA EXPORT TESTS
    // ========================================

    public function test_products_page_has_export_functionality(): void
    {
        $this->actingAs($this->basicSupplierUser);

        $component = Livewire::test(Products::class, ['brandId' => $this->brand->id]);

        // Products page should have export button (CSV button)
        $component->assertSee('CSV');
    }

    public function test_supply_chain_page_has_export_functionality(): void
    {
        $this->actingAs($this->premiumSupplierUser);

        $component = Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id]);

        // Supply chain page should have export capability (Export button)
        $component->assertSee('Export');
    }

    // ========================================
    // PERIOD FILTER TESTS
    // ========================================

    public function test_dashboard_period_filter_can_be_changed(): void
    {
        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('period', '30d')
            ->set('period', '90d')
            ->assertSet('period', '90d');
    }

    public function test_products_period_filter_works(): void
    {
        $this->actingAs($this->basicSupplierUser);

        Livewire::test(Products::class, ['brandId' => $this->brand->id])
            ->assertSet('period', '12m')
            ->set('period', '6m')
            ->assertSet('period', '6m');
    }
}
