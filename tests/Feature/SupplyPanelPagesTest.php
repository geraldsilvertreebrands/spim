<?php

namespace Tests\Feature;

use App\Filament\SupplyPanel\Pages\Benchmarks;
use App\Filament\SupplyPanel\Pages\Cohorts;
use App\Filament\SupplyPanel\Pages\CustomerEngagement;
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
 * Tests for Supply Panel pages (D-006 to D-009).
 *
 * Verifies that:
 * - Products page is accessible and displays correctly
 * - Trends page is accessible and displays correctly
 * - Benchmarks page is accessible and displays correctly
 * - Premium Features page shows for basic users, redirects premium users
 */
class SupplyPanelPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $supplierUser;

    private User $premiumUser;

    private User $petHeavenPremiumUser;

    private Brand $brand;

    private Brand $competitorBrand;

    private Brand $petHeavenBrand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Create admin user
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Create basic supplier user
        $this->supplierUser = User::factory()->create();
        $this->supplierUser->assignRole('supplier-basic');

        // Create premium supplier user
        $this->premiumUser = User::factory()->create();
        $this->premiumUser->assignRole('supplier-premium');

        // Create brands (explicitly non-Pet Heaven for consistent testing)
        $this->brand = Brand::factory()->create(['name' => 'Test Brand', 'company_id' => 3]); // FtN
        $this->competitorBrand = Brand::factory()->create(['name' => 'Competitor Brand', 'company_id' => 3]); // FtN
        $this->petHeavenBrand = Brand::factory()->create(['name' => 'Pet Heaven Test', 'company_id' => 9]); // 9 = Pet Heaven

        // Create Pet Heaven premium user
        $this->petHeavenPremiumUser = User::factory()->create();
        $this->petHeavenPremiumUser->assignRole('supplier-premium');
        $this->petHeavenPremiumUser->brands()->attach($this->petHeavenBrand);

        // Associate suppliers with brand
        $this->supplierUser->brands()->attach($this->brand);
        $this->premiumUser->brands()->attach($this->brand);

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
            'sell_in' => [
                [
                    'sku' => 'SKU001',
                    'name' => 'Product 1',
                    'months' => ['2024-01' => 100, '2024-02' => 120, '2024-03' => 110],
                ],
            ],
            'sell_out' => [
                [
                    'sku' => 'SKU001',
                    'name' => 'Product 1',
                    'months' => ['2024-01' => 80, '2024-02' => 95, '2024-03' => 100],
                ],
            ],
            'closing_stock' => [
                [
                    'sku' => 'SKU001',
                    'name' => 'Product 1',
                    'months' => ['2024-01' => 50, '2024-02' => 75, '2024-03' => 85],
                ],
            ],
        ]);
        $mockBQ->method('getPurchaseOrders')->willReturn([
            'summary' => [
                'total_pos' => 25,
                'on_time_pct' => 88.5,
                'in_full_pct' => 92.0,
                'otif_pct' => 84.0,
            ],
            'monthly' => [
                ['month' => '2024-01', 'po_count' => 8, 'on_time_pct' => 87.5, 'in_full_pct' => 100.0, 'otif_pct' => 87.5],
                ['month' => '2024-02', 'po_count' => 10, 'on_time_pct' => 90.0, 'in_full_pct' => 90.0, 'otif_pct' => 80.0],
                ['month' => '2024-03', 'po_count' => 7, 'on_time_pct' => 85.7, 'in_full_pct' => 85.7, 'otif_pct' => 85.7],
            ],
            'orders' => [
                [
                    'po_number' => 'PO-2024-001',
                    'order_date' => '2024-01-15',
                    'expected_delivery_date' => '2024-01-22',
                    'actual_delivery_date' => '2024-01-21',
                    'status' => 'delivered',
                    'line_count' => 5,
                    'total_value' => 15000.0,
                    'delivered_on_time' => true,
                    'delivered_in_full' => true,
                ],
                [
                    'po_number' => 'PO-2024-002',
                    'order_date' => '2024-02-10',
                    'expected_delivery_date' => '2024-02-17',
                    'actual_delivery_date' => '2024-02-20',
                    'status' => 'delivered',
                    'line_count' => 3,
                    'total_value' => 8500.0,
                    'delivered_on_time' => false,
                    'delivered_in_full' => true,
                ],
            ],
        ]);
        $mockBQ->method('getPurchaseOrderLines')->willReturn([
            [
                'line_number' => 1,
                'sku' => 'SKU001',
                'product_name' => 'Product 1',
                'quantity_ordered' => 100,
                'quantity_delivered' => 100,
                'unit_price' => 50.0,
                'delivery_status' => 'delivered',
            ],
            [
                'line_number' => 2,
                'sku' => 'SKU002',
                'product_name' => 'Product 2',
                'quantity_ordered' => 50,
                'quantity_delivered' => 45,
                'unit_price' => 100.0,
                'delivery_status' => 'partial',
            ],
        ]);
        $mockBQ->method('getCustomerEngagement')->willReturn([
            [
                'sku' => 'SKU001',
                'name' => 'Product 1',
                'avg_qty_per_order' => 2.5,
                'reorder_rate' => 35.0,
                'avg_frequency_months' => 2.3,
                'promo_intensity' => 15.0,
            ],
            [
                'sku' => 'SKU002',
                'name' => 'Product 2',
                'avg_qty_per_order' => 1.2,
                'reorder_rate' => 18.0,
                'avg_frequency_months' => 4.1,
                'promo_intensity' => 45.0,
            ],
            [
                'sku' => 'SKU003',
                'name' => 'Product 3',
                'avg_qty_per_order' => 3.8,
                'reorder_rate' => 8.5,
                'avg_frequency_months' => null,
                'promo_intensity' => 60.0,
            ],
        ]);
        $mockBQ->method('getSalesForecast')->willReturn([
            'historical' => [
                ['month' => '2024-01', 'revenue' => 100000.0, 'units' => 1000],
                ['month' => '2024-02', 'revenue' => 105000.0, 'units' => 1050],
                ['month' => '2024-03', 'revenue' => 110000.0, 'units' => 1100],
                ['month' => '2024-04', 'revenue' => 108000.0, 'units' => 1080],
                ['month' => '2024-05', 'revenue' => 115000.0, 'units' => 1150],
                ['month' => '2024-06', 'revenue' => 120000.0, 'units' => 1200],
            ],
            'forecast' => [
                ['month' => '2024-07', 'baseline' => 122000.0, 'optimistic' => 140300.0, 'pessimistic' => 109800.0, 'lower_bound' => 110000.0, 'upper_bound' => 134000.0],
                ['month' => '2024-08', 'baseline' => 125000.0, 'optimistic' => 143750.0, 'pessimistic' => 112500.0, 'lower_bound' => 112000.0, 'upper_bound' => 138000.0],
                ['month' => '2024-09', 'baseline' => 128000.0, 'optimistic' => 147200.0, 'pessimistic' => 115200.0, 'lower_bound' => 114000.0, 'upper_bound' => 142000.0],
            ],
        ]);
        $mockBQ->method('getCohortAnalysis')->willReturn([
            'cohorts' => [
                '2024-01' => [
                    'size' => 100,
                    'retention' => [0 => 100.0, 1 => 35.0, 2 => 28.0, 3 => 22.0, 4 => 18.0, 5 => 15.0, 6 => 12.0],
                    'customers' => [0 => 100, 1 => 35, 2 => 28, 3 => 22, 4 => 18, 5 => 15, 6 => 12],
                    'revenue' => [0 => 15000.0, 1 => 5500.0, 2 => 4200.0, 3 => 3300.0, 4 => 2700.0, 5 => 2250.0, 6 => 1800.0],
                ],
                '2024-02' => [
                    'size' => 120,
                    'retention' => [0 => 100.0, 1 => 38.0, 2 => 30.0, 3 => 25.0, 4 => 20.0, 5 => 17.0],
                    'customers' => [0 => 120, 1 => 46, 2 => 36, 3 => 30, 4 => 24, 5 => 20],
                    'revenue' => [0 => 18000.0, 1 => 6900.0, 2 => 5400.0, 3 => 4500.0, 4 => 3600.0, 5 => 3000.0],
                ],
                '2024-03' => [
                    'size' => 95,
                    'retention' => [0 => 100.0, 1 => 32.0, 2 => 26.0, 3 => 20.0, 4 => 16.0],
                    'customers' => [0 => 95, 1 => 30, 2 => 25, 3 => 19, 4 => 15],
                    'revenue' => [0 => 14250.0, 1 => 4500.0, 2 => 3750.0, 3 => 2850.0, 4 => 2250.0],
                ],
                '2024-04' => [
                    'size' => 110,
                    'retention' => [0 => 100.0, 1 => 40.0, 2 => 33.0, 3 => 27.0],
                    'customers' => [0 => 110, 1 => 44, 2 => 36, 3 => 30],
                    'revenue' => [0 => 16500.0, 1 => 6600.0, 2 => 5400.0, 3 => 4500.0],
                ],
            ],
            'months' => ['2024-01', '2024-02', '2024-03', '2024-04'],
        ]);
        $mockBQ->method('getRfmAnalysis')->willReturn([
            'segments' => [
                'Champions' => ['count' => 150, 'avg_revenue' => 2500.0, 'r_avg' => 4.8, 'f_avg' => 4.5, 'm_avg' => 4.7],
                'Loyal Customers' => ['count' => 200, 'avg_revenue' => 1800.0, 'r_avg' => 3.5, 'f_avg' => 4.2, 'm_avg' => 3.8],
                'Potential Loyalists' => ['count' => 180, 'avg_revenue' => 1200.0, 'r_avg' => 4.2, 'f_avg' => 2.8, 'm_avg' => 3.2],
                'New Customers' => ['count' => 120, 'avg_revenue' => 800.0, 'r_avg' => 4.5, 'f_avg' => 1.5, 'm_avg' => 2.5],
                'At Risk' => ['count' => 90, 'avg_revenue' => 1500.0, 'r_avg' => 1.8, 'f_avg' => 3.5, 'm_avg' => 3.2],
                'Hibernating' => ['count' => 60, 'avg_revenue' => 600.0, 'r_avg' => 1.5, 'f_avg' => 1.8, 'm_avg' => 3.5],
                'Lost' => ['count' => 50, 'avg_revenue' => 400.0, 'r_avg' => 1.2, 'f_avg' => 1.2, 'm_avg' => 1.5],
            ],
            'matrix' => [
                ['r_score' => 5, 'f_score' => 5, 'm_score' => 5, 'count' => 50],
                ['r_score' => 5, 'f_score' => 4, 'm_score' => 5, 'count' => 40],
                ['r_score' => 4, 'f_score' => 4, 'm_score' => 4, 'count' => 60],
                ['r_score' => 3, 'f_score' => 3, 'm_score' => 3, 'count' => 100],
                ['r_score' => 2, 'f_score' => 2, 'm_score' => 2, 'count' => 80],
                ['r_score' => 1, 'f_score' => 1, 'm_score' => 1, 'count' => 50],
            ],
        ]);
        $mockBQ->method('getRetentionAnalysis')->willReturn([
            'retention' => [
                ['month' => '2024-02', 'retained' => 450, 'churned' => 150, 'retention_rate' => 75.0, 'churn_rate' => 25.0],
                ['month' => '2024-03', 'retained' => 420, 'churned' => 180, 'retention_rate' => 70.0, 'churn_rate' => 30.0],
                ['month' => '2024-04', 'retained' => 480, 'churned' => 120, 'retention_rate' => 80.0, 'churn_rate' => 20.0],
                ['month' => '2024-05', 'retained' => 460, 'churned' => 140, 'retention_rate' => 76.7, 'churn_rate' => 23.3],
                ['month' => '2024-06', 'retained' => 500, 'churned' => 100, 'retention_rate' => 83.3, 'churn_rate' => 16.7],
            ],
        ]);
        $mockBQ->method('getProductList')->willReturn([
            ['sku' => 'SKU001', 'name' => 'Premium Vitamin C'],
            ['sku' => 'SKU002', 'name' => 'Organic Protein Powder'],
            ['sku' => 'SKU003', 'name' => 'Natural Shampoo'],
        ]);
        $mockBQ->method('getProductDeepDive')->willReturn([
            'product_info' => [
                'sku' => 'SKU001',
                'name' => 'Premium Vitamin C',
                'category' => 'Health & Wellness',
                'subcategory' => 'Vitamins',
            ],
            'performance' => [
                'total_revenue' => 125000.0,
                'total_units' => 2500,
                'total_orders' => 1800,
                'avg_price' => 50.0,
                'avg_order_value' => 69.44,
            ],
            'customer' => [
                'unique_customers' => 1200,
                'avg_qty_per_customer' => 2.1,
                'reorder_rate' => 35.5,
                'avg_customer_span_days' => 45,
            ],
            'price' => [
                'avg_price' => 50.0,
                'min_price' => 42.0,
                'max_price' => 55.0,
                'promo_rate' => 22.5,
                'avg_discount' => 8.0,
            ],
            'trend' => [
                ['month' => '2024-01', 'revenue' => 18000.0, 'orders' => 260, 'units' => 360],
                ['month' => '2024-02', 'revenue' => 20000.0, 'orders' => 290, 'units' => 400],
                ['month' => '2024-03', 'revenue' => 22000.0, 'orders' => 320, 'units' => 440],
                ['month' => '2024-04', 'revenue' => 21000.0, 'orders' => 305, 'units' => 420],
                ['month' => '2024-05', 'revenue' => 23000.0, 'orders' => 335, 'units' => 460],
                ['month' => '2024-06', 'revenue' => 21000.0, 'orders' => 290, 'units' => 420],
            ],
            'comparison' => [
                'revenue_vs_avg' => 45.2,
                'orders_vs_avg' => 38.5,
                'units_vs_avg' => 52.0,
                'brand_avg_revenue' => 86000.0,
                'brand_avg_orders' => 1300.0,
                'brand_avg_units' => 1645.0,
            ],
        ]);
        $mockBQ->method('getMarketingAnalytics')->willReturn([
            'summary' => [
                'promo_revenue' => 45000.0,
                'regular_revenue' => 80000.0,
                'total_revenue' => 125000.0,
                'promo_revenue_pct' => 36.0,
                'promo_orders' => 180,
                'regular_orders' => 270,
                'total_orders' => 450,
                'promo_orders_pct' => 40.0,
                'total_discount_given' => 8500.0,
                'avg_discount_amount' => 47.22,
                'avg_discount_pct' => 15.5,
            ],
            'campaigns' => [
                ['discount_tier' => '0-10%', 'revenue' => 15000.0, 'orders' => 60, 'units' => 150, 'discount_given' => 1200.0, 'effective_discount_pct' => 7.5],
                ['discount_tier' => '10-20%', 'revenue' => 18000.0, 'orders' => 70, 'units' => 180, 'discount_given' => 3200.0, 'effective_discount_pct' => 15.0],
                ['discount_tier' => '20-30%', 'revenue' => 8000.0, 'orders' => 35, 'units' => 90, 'discount_given' => 2800.0, 'effective_discount_pct' => 25.0],
                ['discount_tier' => '30-50%', 'revenue' => 4000.0, 'orders' => 15, 'units' => 40, 'discount_given' => 1300.0, 'effective_discount_pct' => 35.0],
            ],
            'discount_analysis' => [
                'promo' => ['avg_order_value' => 250.0, 'avg_units_per_order' => 2.5, 'unique_customers' => 150],
                'regular' => ['avg_order_value' => 296.3, 'avg_units_per_order' => 1.8, 'unique_customers' => 220],
            ],
            'monthly_trend' => [
                ['month' => '2024-01', 'promo_revenue' => 12000.0, 'regular_revenue' => 22000.0, 'promo_orders' => 45, 'regular_orders' => 75],
                ['month' => '2024-02', 'promo_revenue' => 14000.0, 'regular_revenue' => 26000.0, 'promo_orders' => 55, 'regular_orders' => 85],
                ['month' => '2024-03', 'promo_revenue' => 19000.0, 'regular_revenue' => 32000.0, 'promo_orders' => 80, 'regular_orders' => 110],
            ],
        ]);
        $mockBQ->method('getSubscriptionOverview')->willReturn([
            'summary' => [
                'total_subscriptions' => 500,
                'active_subscriptions' => 400,
                'cancelled_subscriptions' => 80,
                'paused_subscriptions' => 20,
                'subscribers' => 350,
                'avg_subscription_value' => 250.0,
                'mrr' => 100000.0,
                'arr' => 1200000.0,
                'avg_lifetime_days' => 180.0,
                'avg_ltv' => 1500.0,
                'churn_rate' => 16.0,
                'retention_rate' => 80.0,
            ],
            'monthly' => [
                ['month' => '2024-01', 'new_subscriptions' => 50, 'churned' => 15, 'reactivated' => 5, 'net_change' => 40, 'new_mrr' => 12500.0, 'lost_mrr' => 3750.0, 'net_mrr' => 8750.0],
                ['month' => '2024-02', 'new_subscriptions' => 60, 'churned' => 20, 'reactivated' => 8, 'net_change' => 48, 'new_mrr' => 15000.0, 'lost_mrr' => 5000.0, 'net_mrr' => 10000.0],
                ['month' => '2024-03', 'new_subscriptions' => 55, 'churned' => 18, 'reactivated' => 6, 'net_change' => 43, 'new_mrr' => 13750.0, 'lost_mrr' => 4500.0, 'net_mrr' => 9250.0],
            ],
            'by_frequency' => [
                ['frequency' => 'Monthly', 'count' => 200, 'total_value' => 50000.0, 'avg_value' => 250.0],
                ['frequency' => 'Bi-Weekly', 'count' => 120, 'total_value' => 36000.0, 'avg_value' => 300.0],
                ['frequency' => 'Weekly', 'count' => 80, 'total_value' => 14000.0, 'avg_value' => 175.0],
            ],
        ]);
        $mockBQ->method('getSubscriptionProducts')->willReturn([
            [
                'product_id' => 'PH001',
                'sku' => 'PH-DOG-FOOD-001',
                'product_name' => 'Premium Dog Food',
                'category' => 'Pet Food',
                'total_subscriptions' => 150,
                'active_subscriptions' => 120,
                'cancelled_subscriptions' => 25,
                'mrr' => 30000.0,
                'avg_subscription_value' => 250.0,
                'subscribers' => 110,
                'avg_ltv' => 1800.0,
                'churn_rate' => 16.7,
            ],
            [
                'product_id' => 'PH002',
                'sku' => 'PH-CAT-FOOD-001',
                'product_name' => 'Premium Cat Food',
                'category' => 'Pet Food',
                'total_subscriptions' => 100,
                'active_subscriptions' => 85,
                'cancelled_subscriptions' => 12,
                'mrr' => 21250.0,
                'avg_subscription_value' => 250.0,
                'subscribers' => 80,
                'avg_ltv' => 1600.0,
                'churn_rate' => 12.0,
            ],
        ]);
        $mockBQ->method('getSubscriptionPredictions')->willReturn([
            'upcoming' => [
                [
                    'subscription_id' => 'SUB001',
                    'customer_id' => 'CUST001',
                    'customer_name' => 'John Doe',
                    'sku' => 'PH-DOG-FOOD-001',
                    'product_name' => 'Premium Dog Food',
                    'next_delivery_date' => '2024-07-10',
                    'subscription_value' => 250.0,
                    'delivery_frequency' => 'Monthly',
                    'orders_to_date' => 6,
                    'days_until_delivery' => 5,
                ],
                [
                    'subscription_id' => 'SUB002',
                    'customer_id' => 'CUST002',
                    'customer_name' => 'Jane Smith',
                    'sku' => 'PH-CAT-FOOD-001',
                    'product_name' => 'Premium Cat Food',
                    'next_delivery_date' => '2024-07-15',
                    'subscription_value' => 200.0,
                    'delivery_frequency' => 'Monthly',
                    'orders_to_date' => 4,
                    'days_until_delivery' => 10,
                ],
            ],
            'at_risk' => [
                [
                    'subscription_id' => 'SUB003',
                    'customer_id' => 'CUST003',
                    'customer_name' => 'Bob Wilson',
                    'sku' => 'PH-DOG-FOOD-001',
                    'product_name' => 'Premium Dog Food',
                    'subscription_value' => 250.0,
                    'last_order_date' => '2024-04-15',
                    'days_since_last_order' => 80,
                    'skip_count' => 2,
                    'total_orders' => 3,
                    'risk_reason' => 'Overdue',
                ],
            ],
            'summary' => [
                'deliveries_next_7_days' => 45,
                'deliveries_next_30_days' => 180,
                'revenue_next_7_days' => 11250.0,
                'revenue_next_30_days' => 45000.0,
                'at_risk_count' => 1,
                'at_risk_mrr' => 250.0,
            ],
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

    // =====================================================
    // Products Page Tests (D-006)
    // =====================================================

    public function test_products_page_loads_for_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(Products::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_products_page_loads_for_admin(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(Products::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false);
    }

    public function test_products_page_shows_period_options(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(Products::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey('3m', $periods);
        $this->assertArrayHasKey('6m', $periods);
        $this->assertArrayHasKey('12m', $periods);
    }

    public function test_products_page_period_change_triggers_reload(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(Products::class, ['brandId' => $this->brand->id])
            ->set('period', '6m')
            ->assertSet('period', '6m')
            ->assertSet('loading', false);
    }

    // =====================================================
    // Trends Page Tests (D-007)
    // =====================================================

    public function test_trends_page_loads_for_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(Trends::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_trends_page_loads_chart_data(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(Trends::class, ['brandId' => $this->brand->id]);

        // Check that chart data is populated
        $this->assertNotEmpty($component->get('revenueChartData.labels'));
        $this->assertNotEmpty($component->get('revenueChartData.datasets'));
    }

    public function test_trends_page_has_period_filter(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(Trends::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey('12m', $periods);
        $this->assertArrayHasKey('24m', $periods);
    }

    // =====================================================
    // Benchmarks Page Tests (D-008)
    // =====================================================

    public function test_benchmarks_page_loads_for_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(Benchmarks::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false);
    }

    public function test_benchmarks_page_uses_anonymized_competitor_labels(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(Benchmarks::class, ['brandId' => $this->brand->id]);

        $labels = $component->get('competitorLabels');
        $this->assertContains('Competitor A', $labels);
        $this->assertContains('Competitor B', $labels);
        $this->assertContains('Competitor C', $labels);
        // Should not contain real brand name
        $this->assertNotContains('Competitor Brand', $labels);
    }

    public function test_benchmarks_page_shows_error_without_competitors(): void
    {
        // Remove competitor relationship
        BrandCompetitor::where('brand_id', $this->brand->id)->delete();

        $this->actingAs($this->supplierUser);

        Livewire::test(Benchmarks::class, ['brandId' => $this->brand->id])
            ->assertSet('error', 'No competitor brands have been configured for benchmarking.');
    }

    // =====================================================
    // Premium Features Page Tests (D-009)
    // =====================================================

    public function test_premium_features_page_loads_for_basic_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PremiumFeatures::class);

        // Basic user should see the page
        $features = $component->get('features');
        $this->assertNotEmpty($features);
    }

    public function test_premium_features_page_lists_all_premium_features(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PremiumFeatures::class);

        $features = $component->get('features');

        // Check that key features are listed
        $featureNames = array_column($features, 'name');
        $this->assertContains('Forecasting', $featureNames);
        $this->assertContains('Cohort Analysis', $featureNames);
        $this->assertContains('RFM Segmentation', $featureNames);
    }

    public function test_premium_features_page_shows_contact_information(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PremiumFeatures::class);

        $email = $component->invade()->getContactEmail();
        $phone = $component->invade()->getContactPhone();

        $this->assertNotEmpty($email);
        $this->assertNotEmpty($phone);
    }

    // =====================================================
    // Brand Access Tests
    // =====================================================

    public function test_supplier_cannot_access_other_brand_data(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->supplierUser);

        Livewire::test(Products::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_admin_can_access_any_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->adminUser);

        Livewire::test(Products::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    // =====================================================
    // Supply Chain Page Tests (D-011)
    // =====================================================

    public function test_supply_chain_page_loads_for_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_supply_chain_page_loads_sell_in_data(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id]);

        $this->assertNotEmpty($component->get('sellInData'));
        $this->assertEquals('SKU001', $component->get('sellInData.0.sku'));
    }

    public function test_supply_chain_page_loads_sell_out_data(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id]);

        $this->assertNotEmpty($component->get('sellOutData'));
        $this->assertEquals('SKU001', $component->get('sellOutData.0.sku'));
    }

    public function test_supply_chain_page_loads_closing_stock_data(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id]);

        $this->assertNotEmpty($component->get('closingStockData'));
        $this->assertEquals('SKU001', $component->get('closingStockData.0.sku'));
    }

    public function test_supply_chain_page_extracts_months(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id]);

        $months = $component->get('months');
        $this->assertContains('2024-01', $months);
        $this->assertContains('2024-02', $months);
        $this->assertContains('2024-03', $months);
    }

    public function test_supply_chain_page_has_period_options(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(SupplyChain::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey('3m', $periods);
        $this->assertArrayHasKey('6m', $periods);
        $this->assertArrayHasKey('12m', $periods);
        $this->assertArrayHasKey('24m', $periods);
    }

    public function test_supply_chain_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->supplierUser);

        Livewire::test(SupplyChain::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    // =====================================================
    // Purchase Orders Page Tests (D-012)
    // =====================================================

    public function test_purchase_orders_page_loads_for_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_purchase_orders_page_shows_summary_kpis(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id]);

        $summary = $component->get('summary');
        $this->assertEquals(25, $summary['total_pos']);
        $this->assertEquals(88.5, $summary['on_time_pct']);
        $this->assertEquals(92.0, $summary['in_full_pct']);
        $this->assertEquals(84.0, $summary['otif_pct']);
    }

    public function test_purchase_orders_page_loads_orders(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id]);

        $orders = $component->get('orders');
        $this->assertCount(2, $orders);
        $this->assertEquals('PO-2024-001', $orders[0]['po_number']);
    }

    public function test_purchase_orders_page_builds_chart_data(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id]);

        $chartData = $component->get('chartData');
        $this->assertNotEmpty($chartData['labels']);
        $this->assertCount(3, $chartData['datasets']); // bar + 2 lines
    }

    public function test_purchase_orders_page_can_open_detail_modal(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id])
            ->call('openPoDetail', 'PO-2024-001')
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedPoNumber', 'PO-2024-001');
    }

    public function test_purchase_orders_page_loads_po_lines(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id])
            ->call('openPoDetail', 'PO-2024-001');

        $lines = $component->get('selectedPoLines');
        $this->assertCount(2, $lines);
        $this->assertEquals('SKU001', $lines[0]['sku']);
    }

    public function test_purchase_orders_page_can_close_detail_modal(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id])
            ->call('openPoDetail', 'PO-2024-001')
            ->assertSet('showDetailModal', true)
            ->call('closePoDetail')
            ->assertSet('showDetailModal', false)
            ->assertSet('selectedPoNumber', null);
    }

    public function test_purchase_orders_page_has_period_options(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey('3m', $periods);
        $this->assertArrayHasKey('12m', $periods);
    }

    public function test_purchase_orders_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->supplierUser);

        Livewire::test(PurchaseOrders::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_purchase_orders_page_status_badge_classes(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(PurchaseOrders::class, ['brandId' => $this->brand->id]);

        // Test various status badge classes
        $deliveredClass = $component->invade()->getStatusBadgeClass('delivered');
        $this->assertStringContainsString('green', $deliveredClass);

        $pendingClass = $component->invade()->getStatusBadgeClass('pending');
        $this->assertStringContainsString('blue', $pendingClass);

        $partialClass = $component->invade()->getStatusBadgeClass('partial');
        $this->assertStringContainsString('yellow', $partialClass);
    }

    // =====================================================
    // Market Share Page Tests (D-014)
    // =====================================================

    public function test_market_share_page_loads_for_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        Livewire::test(MarketShare::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_market_share_page_builds_category_tree(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        $tree = $component->get('categoryTree');
        $this->assertNotEmpty($tree);
        $this->assertArrayHasKey('Health & Beauty', $tree);
    }

    public function test_market_share_page_has_competitor_labels(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        $labels = $component->get('competitorLabels');
        $this->assertContains('Competitor A', $labels);
        $this->assertContains('Competitor B', $labels);
        $this->assertContains('Competitor C', $labels);
    }

    public function test_market_share_page_can_toggle_category(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        // Initially not expanded
        $this->assertNotContains('Health & Beauty', $component->get('expandedCategories'));

        // Toggle to expand
        $component->call('toggleCategory', 'Health & Beauty');
        $this->assertContains('Health & Beauty', $component->get('expandedCategories'));

        // Toggle to collapse
        $component->call('toggleCategory', 'Health & Beauty');
        $this->assertNotContains('Health & Beauty', $component->get('expandedCategories'));
    }

    public function test_market_share_page_can_expand_all(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        $component->call('expandAll');

        $expanded = $component->get('expandedCategories');
        $tree = $component->get('categoryTree');

        $this->assertCount(count($tree), $expanded);
    }

    public function test_market_share_page_can_collapse_all(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        // First expand
        $component->call('expandAll');
        $this->assertNotEmpty($component->get('expandedCategories'));

        // Then collapse
        $component->call('collapseAll');
        $this->assertEmpty($component->get('expandedCategories'));
    }

    public function test_market_share_page_search_filters_categories(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        // Set search term
        $component->set('search', 'Health');

        $filtered = $component->invade()->getFilteredTree();
        $this->assertArrayHasKey('Health & Beauty', $filtered);
    }

    public function test_market_share_page_search_returns_empty_for_no_match(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        // Set search term that won't match
        $component->set('search', 'NonExistentCategory');

        $filtered = $component->invade()->getFilteredTree();
        $this->assertEmpty($filtered);
    }

    public function test_market_share_page_has_period_options(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey('30d', $periods);
        $this->assertArrayHasKey('90d', $periods);
        $this->assertArrayHasKey('1yr', $periods);
    }

    public function test_market_share_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->supplierUser);

        Livewire::test(MarketShare::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_market_share_page_shows_error_without_competitors(): void
    {
        // Remove competitor relationship
        BrandCompetitor::where('brand_id', $this->brand->id)->delete();

        $this->actingAs($this->supplierUser);

        Livewire::test(MarketShare::class, ['brandId' => $this->brand->id])
            ->assertSet('error', 'No competitor brands have been configured for market share analysis.');
    }

    public function test_market_share_page_is_expanded_check(): void
    {
        $this->actingAs($this->supplierUser);

        $component = Livewire::test(MarketShare::class, ['brandId' => $this->brand->id]);

        // Initially not expanded
        $this->assertFalse($component->invade()->isExpanded('Health & Beauty'));

        // After toggle, should be expanded
        $component->call('toggleCategory', 'Health & Beauty');
        $this->assertTrue($component->invade()->isExpanded('Health & Beauty'));
    }

    // =====================================================
    // Customer Engagement Page Tests (D-013)
    // =====================================================

    public function test_customer_engagement_page_loads_for_premium_supplier(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_customer_engagement_page_loads_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        $data = $component->get('engagementData');
        $this->assertCount(3, $data);
        $this->assertEquals('SKU001', $data[0]['sku']);
    }

    public function test_customer_engagement_page_has_all_metrics(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        $data = $component->get('engagementData.0');
        $this->assertArrayHasKey('avg_qty_per_order', $data);
        $this->assertArrayHasKey('reorder_rate', $data);
        $this->assertArrayHasKey('avg_frequency_months', $data);
        $this->assertArrayHasKey('promo_intensity', $data);
    }

    public function test_customer_engagement_page_sorting_works(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        // Default sort is by SKU ascending
        $this->assertEquals('sku', $component->get('sortColumn'));
        $this->assertEquals('asc', $component->get('sortDirection'));

        // Sort by reorder_rate
        $component->call('sortBy', 'reorder_rate');
        $this->assertEquals('reorder_rate', $component->get('sortColumn'));
        $this->assertEquals('asc', $component->get('sortDirection'));

        // Sort again to toggle direction
        $component->call('sortBy', 'reorder_rate');
        $this->assertEquals('desc', $component->get('sortDirection'));
    }

    public function test_customer_engagement_page_has_metric_definitions(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        $definitions = $component->invade()->getMetricDefinitions();
        $this->assertArrayHasKey('avg_qty_per_order', $definitions);
        $this->assertArrayHasKey('reorder_rate', $definitions);
        $this->assertArrayHasKey('avg_frequency_months', $definitions);
        $this->assertArrayHasKey('promo_intensity', $definitions);

        // Ensure each has title and description
        foreach ($definitions as $def) {
            $this->assertArrayHasKey('title', $def);
            $this->assertArrayHasKey('description', $def);
        }
    }

    public function test_customer_engagement_page_format_metrics(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        // Test formatting
        $this->assertEquals('2.50', $component->invade()->formatMetric('avg_qty_per_order', 2.5));
        $this->assertEquals('35.0%', $component->invade()->formatMetric('reorder_rate', 35.0));
        $this->assertEquals('2.3 mo', $component->invade()->formatMetric('avg_frequency_months', 2.3));
        $this->assertEquals('15.0%', $component->invade()->formatMetric('promo_intensity', 15.0));
        $this->assertEquals('-', $component->invade()->formatMetric('avg_frequency_months', null));
    }

    public function test_customer_engagement_page_reorder_rate_colors(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        // Green for >= 30
        $this->assertStringContainsString('green', $component->invade()->getReorderRateColor(35.0));

        // Yellow for 15-30
        $this->assertStringContainsString('yellow', $component->invade()->getReorderRateColor(20.0));

        // Red for < 15
        $this->assertStringContainsString('red', $component->invade()->getReorderRateColor(10.0));
    }

    public function test_customer_engagement_page_promo_intensity_colors(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        // Green for <= 20
        $this->assertStringContainsString('green', $component->invade()->getPromoIntensityColor(15.0));

        // Yellow for 20-50
        $this->assertStringContainsString('yellow', $component->invade()->getPromoIntensityColor(35.0));

        // Red for > 50
        $this->assertStringContainsString('red', $component->invade()->getPromoIntensityColor(60.0));
    }

    public function test_customer_engagement_page_has_period_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey('6m', $periods);
        $this->assertArrayHasKey('12m', $periods);
    }

    public function test_customer_engagement_page_period_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id])
            ->set('period', '6m')
            ->assertSet('period', '6m')
            ->assertSet('loading', false);
    }

    public function test_customer_engagement_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->premiumUser);

        Livewire::test(CustomerEngagement::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_customer_engagement_page_admin_can_access(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_customer_engagement_page_sort_icon_class(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        // Active column should have primary color
        $this->assertStringContainsString('primary', $component->invade()->getSortIconClass('sku'));

        // Inactive column should have gray color
        $this->assertStringContainsString('gray', $component->invade()->getSortIconClass('reorder_rate'));
    }

    public function test_customer_engagement_page_sort_icon_direction(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(CustomerEngagement::class, ['brandId' => $this->brand->id]);

        // Default column shows up arrow
        $this->assertEquals('↑', $component->invade()->getSortIcon('sku'));

        // Non-sorted column shows bidirectional
        $this->assertEquals('↕', $component->invade()->getSortIcon('reorder_rate'));

        // Sort descending
        $component->call('sortBy', 'sku');
        $this->assertEquals('↓', $component->invade()->getSortIcon('sku'));
    }

    // =====================================================
    // Forecasting Page Tests (D-015)
    // =====================================================

    public function test_forecasting_page_loads_for_premium_supplier(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Forecasting::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_forecasting_page_loads_historical_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $historical = $component->get('historicalData');
        $this->assertCount(6, $historical);
        $this->assertEquals('2024-01', $historical[0]['month']);
        $this->assertEquals(100000.0, $historical[0]['revenue']);
    }

    public function test_forecasting_page_loads_forecast_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $forecast = $component->get('forecastData');
        $this->assertCount(3, $forecast);
        $this->assertEquals('2024-07', $forecast[0]['month']);
        $this->assertArrayHasKey('baseline', $forecast[0]);
        $this->assertArrayHasKey('optimistic', $forecast[0]);
        $this->assertArrayHasKey('pessimistic', $forecast[0]);
    }

    public function test_forecasting_page_has_confidence_intervals(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $forecast = $component->get('forecastData.0');
        $this->assertArrayHasKey('lower_bound', $forecast);
        $this->assertArrayHasKey('upper_bound', $forecast);
        $this->assertLessThan($forecast['upper_bound'], $forecast['lower_bound']);
    }

    public function test_forecasting_page_builds_chart_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $chartData = $component->get('chartData');
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(9, $chartData['labels']); // 6 historical + 3 forecast
    }

    public function test_forecasting_page_calculates_summary_stats(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        $this->assertArrayHasKey('avg_historical', $stats);
        $this->assertArrayHasKey('total_forecast_baseline', $stats);
        $this->assertArrayHasKey('total_forecast_optimistic', $stats);
        $this->assertArrayHasKey('total_forecast_pessimistic', $stats);
        $this->assertArrayHasKey('growth_rate', $stats);
        $this->assertArrayHasKey('trend', $stats);
    }

    public function test_forecasting_page_has_scenario_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $scenarios = $component->invade()->getScenarioOptions();
        $this->assertArrayHasKey('baseline', $scenarios);
        $this->assertArrayHasKey('optimistic', $scenarios);
        $this->assertArrayHasKey('pessimistic', $scenarios);
        $this->assertArrayHasKey('all', $scenarios);
    }

    public function test_forecasting_page_has_period_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getForecastPeriodOptions();
        $this->assertArrayHasKey(3, $periods);
        $this->assertArrayHasKey(6, $periods);
        $this->assertArrayHasKey(12, $periods);
    }

    public function test_forecasting_page_scenario_change_works(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Forecasting::class, ['brandId' => $this->brand->id])
            ->set('scenario', 'optimistic')
            ->assertSet('scenario', 'optimistic');
    }

    public function test_forecasting_page_format_currency(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('R500', $component->invade()->formatCurrency(500));
        $this->assertEquals('R1.5K', $component->invade()->formatCurrency(1500));
        $this->assertEquals('R1.2M', $component->invade()->formatCurrency(1200000));
    }

    public function test_forecasting_page_trend_icon(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $icon = $component->invade()->getTrendIcon();
        $this->assertContains($icon, ['↑', '↓', '→']);
    }

    public function test_forecasting_page_trend_color_class(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        $colorClass = $component->invade()->getTrendColorClass();
        $this->assertMatchesRegularExpression('/text-(green|red|gray)-/', $colorClass);
    }

    public function test_forecasting_page_selected_scenario_total(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Forecasting::class, ['brandId' => $this->brand->id]);

        // Default is baseline
        $baselineTotal = $component->invade()->getSelectedScenarioTotal();
        $this->assertGreaterThan(0, $baselineTotal);

        // Change to optimistic
        $component->set('scenario', 'optimistic');
        $optimisticTotal = $component->invade()->getSelectedScenarioTotal();
        $this->assertGreaterThan($baselineTotal, $optimisticTotal);
    }

    public function test_forecasting_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->premiumUser);

        Livewire::test(Forecasting::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_forecasting_page_admin_can_access(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(Forecasting::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    // =====================================================
    // Cohort Analysis Page Tests (D-016)
    // =====================================================

    public function test_cohorts_page_loads_for_premium_supplier(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Cohorts::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_cohorts_page_loads_cohort_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $cohortData = $component->get('cohortData');
        $this->assertCount(4, $cohortData);
        $this->assertArrayHasKey('2024-01', $cohortData);
        $this->assertArrayHasKey('2024-02', $cohortData);
    }

    public function test_cohorts_page_has_cohort_months(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $months = $component->get('cohortMonths');
        $this->assertCount(4, $months);
        $this->assertContains('2024-01', $months);
        $this->assertContains('2024-04', $months);
    }

    public function test_cohorts_page_cohort_has_size_and_retention(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $cohort = $component->get('cohortData.2024-01');
        $this->assertEquals(100, $cohort['size']);
        $this->assertArrayHasKey('retention', $cohort);
        $this->assertArrayHasKey('customers', $cohort);
        $this->assertArrayHasKey('revenue', $cohort);
    }

    public function test_cohorts_page_calculates_summary_stats(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        $this->assertArrayHasKey('total_cohorts', $stats);
        $this->assertArrayHasKey('avg_month1_retention', $stats);
        $this->assertArrayHasKey('avg_month3_retention', $stats);
        $this->assertArrayHasKey('avg_month6_retention', $stats);
        $this->assertArrayHasKey('best_cohort', $stats);
        $this->assertArrayHasKey('worst_cohort', $stats);
        $this->assertArrayHasKey('overall_retention_trend', $stats);
    }

    public function test_cohorts_page_identifies_best_cohort(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // Best cohort should be 2024-04 with 40% month-1 retention
        $this->assertEquals('2024-04', $stats['best_cohort']);
    }

    public function test_cohorts_page_identifies_worst_cohort(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // Worst cohort should be 2024-03 with 32% month-1 retention
        $this->assertEquals('2024-03', $stats['worst_cohort']);
    }

    public function test_cohorts_page_has_period_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey(6, $periods);
        $this->assertArrayHasKey(12, $periods);
        $this->assertArrayHasKey(18, $periods);
        $this->assertArrayHasKey(24, $periods);
    }

    public function test_cohorts_page_has_metric_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $metrics = $component->invade()->getMetricOptions();
        $this->assertArrayHasKey('retention', $metrics);
        $this->assertArrayHasKey('customers', $metrics);
        $this->assertArrayHasKey('revenue', $metrics);
    }

    public function test_cohorts_page_metric_change_works(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Cohorts::class, ['brandId' => $this->brand->id])
            ->set('metric', 'customers')
            ->assertSet('metric', 'customers');
    }

    public function test_cohorts_page_period_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Cohorts::class, ['brandId' => $this->brand->id])
            ->set('monthsBack', 6)
            ->assertSet('monthsBack', 6)
            ->assertSet('loading', false);
    }

    public function test_cohorts_page_retention_color_classes(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        // High retention: green
        $this->assertStringContainsString('green-600', $component->invade()->getRetentionColorClass(55.0));

        // Good retention: light green
        $this->assertStringContainsString('green-400', $component->invade()->getRetentionColorClass(35.0));

        // Medium retention: yellow
        $this->assertStringContainsString('yellow-400', $component->invade()->getRetentionColorClass(25.0));

        // Low retention: orange
        $this->assertStringContainsString('orange-400', $component->invade()->getRetentionColorClass(15.0));

        // Very low retention: red
        $this->assertStringContainsString('red-400', $component->invade()->getRetentionColorClass(5.0));
    }

    public function test_cohorts_page_trend_icon(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $icon = $component->invade()->getTrendIcon();
        $this->assertContains($icon, ['↑', '↓', '→']);
    }

    public function test_cohorts_page_trend_color_class(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $colorClass = $component->invade()->getTrendColorClass();
        $this->assertMatchesRegularExpression('/text-(green|red|gray)-/', $colorClass);
    }

    public function test_cohorts_page_format_number(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('500', $component->invade()->formatNumber(500));
        $this->assertEquals('1.5K', $component->invade()->formatNumber(1500));
        $this->assertEquals('1.2M', $component->invade()->formatNumber(1200000));
    }

    public function test_cohorts_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->premiumUser);

        Livewire::test(Cohorts::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_cohorts_page_admin_can_access(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(Cohorts::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_cohorts_page_calculates_avg_month1_retention(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Cohorts::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // Average of 35, 38, 32, 40 = 36.25
        $this->assertEquals(36.3, $stats['avg_month1_retention']);
    }

    // =====================================================
    // RFM Analysis Page Tests (D-017)
    // =====================================================

    public function test_rfm_analysis_page_loads_for_premium_supplier(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_rfm_analysis_page_loads_segments(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $segments = $component->get('segments');
        $this->assertCount(7, $segments);
        $this->assertArrayHasKey('Champions', $segments);
        $this->assertArrayHasKey('At Risk', $segments);
    }

    public function test_rfm_analysis_page_segment_has_required_fields(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $champions = $component->get('segments.Champions');
        $this->assertArrayHasKey('count', $champions);
        $this->assertArrayHasKey('avg_revenue', $champions);
        $this->assertArrayHasKey('r_avg', $champions);
        $this->assertArrayHasKey('f_avg', $champions);
        $this->assertArrayHasKey('m_avg', $champions);
    }

    public function test_rfm_analysis_page_calculates_summary_stats(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        $this->assertArrayHasKey('total_customers', $stats);
        $this->assertArrayHasKey('champions_count', $stats);
        $this->assertArrayHasKey('champions_pct', $stats);
        $this->assertArrayHasKey('at_risk_count', $stats);
        $this->assertArrayHasKey('at_risk_pct', $stats);
        $this->assertArrayHasKey('avg_recency_score', $stats);
        $this->assertArrayHasKey('avg_frequency_score', $stats);
        $this->assertArrayHasKey('avg_monetary_score', $stats);
    }

    public function test_rfm_analysis_page_counts_champions(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        $this->assertEquals(150, $stats['champions_count']);
    }

    public function test_rfm_analysis_page_counts_at_risk_customers(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // At Risk (90) + Hibernating (60) + Lost (50) = 200
        $this->assertEquals(200, $stats['at_risk_count']);
    }

    public function test_rfm_analysis_page_has_period_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $periods = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey(6, $periods);
        $this->assertArrayHasKey(12, $periods);
        $this->assertArrayHasKey(18, $periods);
        $this->assertArrayHasKey(24, $periods);
    }

    public function test_rfm_analysis_page_has_segment_definitions(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $definitions = $component->invade()->getSegmentDefinitions();
        $this->assertArrayHasKey('Champions', $definitions);
        $this->assertArrayHasKey('At Risk', $definitions);
        $this->assertArrayHasKey('Lost', $definitions);

        // Each definition should have description, color, bgColor, action
        $champDef = $definitions['Champions'];
        $this->assertArrayHasKey('description', $champDef);
        $this->assertArrayHasKey('color', $champDef);
        $this->assertArrayHasKey('bgColor', $champDef);
        $this->assertArrayHasKey('action', $champDef);
    }

    public function test_rfm_analysis_page_segment_colors(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $champColor = $component->invade()->getSegmentColor('Champions');
        $this->assertStringContainsString('green', $champColor);

        $atRiskColor = $component->invade()->getSegmentColor('At Risk');
        $this->assertStringContainsString('red', $atRiskColor);
    }

    public function test_rfm_analysis_page_segment_bg_colors(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $champBg = $component->invade()->getSegmentBgColor('Champions');
        $this->assertStringContainsString('green', $champBg);

        $lostBg = $component->invade()->getSegmentBgColor('Lost');
        $this->assertStringContainsString('gray', $lostBg);
    }

    public function test_rfm_analysis_page_builds_chart_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $chartData = $component->invade()->getChartData();
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(7, $chartData['labels']);
    }

    public function test_rfm_analysis_page_format_number(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('500', $component->invade()->formatNumber(500));
        $this->assertEquals('1.5K', $component->invade()->formatNumber(1500));
        $this->assertEquals('1.2M', $component->invade()->formatNumber(1200000));
    }

    public function test_rfm_analysis_page_format_currency(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('R500', $component->invade()->formatCurrency(500));
        $this->assertEquals('R1.5K', $component->invade()->formatCurrency(1500));
        $this->assertEquals('R1.2M', $component->invade()->formatCurrency(1200000));
    }

    public function test_rfm_analysis_page_period_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id])
            ->set('monthsBack', 6)
            ->assertSet('monthsBack', 6)
            ->assertSet('loading', false);
    }

    public function test_rfm_analysis_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->premiumUser);

        Livewire::test(RfmAnalysis::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_rfm_analysis_page_admin_can_access(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_rfm_analysis_page_has_rfm_matrix(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(RfmAnalysis::class, ['brandId' => $this->brand->id]);

        $matrix = $component->get('rfmMatrix');
        $this->assertNotEmpty($matrix);
        $this->assertArrayHasKey('r_score', $matrix[0]);
        $this->assertArrayHasKey('f_score', $matrix[0]);
        $this->assertArrayHasKey('m_score', $matrix[0]);
        $this->assertArrayHasKey('count', $matrix[0]);
    }

    // =====================================================
    // Retention Page Tests (D-018)
    // =====================================================

    public function test_retention_page_loads_for_premium_supplier(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Retention::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_retention_page_loads_retention_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $data = $component->get('retentionData');
        $this->assertCount(5, $data);
        $this->assertEquals('2024-02', $data[0]['month']);
    }

    public function test_retention_page_data_has_required_fields(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $row = $component->get('retentionData.0');
        $this->assertArrayHasKey('month', $row);
        $this->assertArrayHasKey('retained', $row);
        $this->assertArrayHasKey('churned', $row);
        $this->assertArrayHasKey('retention_rate', $row);
        $this->assertArrayHasKey('churn_rate', $row);
    }

    public function test_retention_page_calculates_summary_stats(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        $this->assertArrayHasKey('avg_retention_rate', $stats);
        $this->assertArrayHasKey('avg_churn_rate', $stats);
        $this->assertArrayHasKey('total_retained', $stats);
        $this->assertArrayHasKey('total_churned', $stats);
        $this->assertArrayHasKey('best_period', $stats);
        $this->assertArrayHasKey('worst_period', $stats);
        $this->assertArrayHasKey('trend', $stats);
        $this->assertArrayHasKey('current_retention', $stats);
        $this->assertArrayHasKey('retention_change', $stats);
    }

    public function test_retention_page_identifies_best_period(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // Best period should be 2024-06 with 83.3% retention
        $this->assertEquals('2024-06', $stats['best_period']);
    }

    public function test_retention_page_identifies_worst_period(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // Worst period should be 2024-03 with 70% retention
        $this->assertEquals('2024-03', $stats['worst_period']);
    }

    public function test_retention_page_calculates_current_retention(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // Current should be 2024-06 with 83.3%
        $this->assertEquals(83.3, $stats['current_retention']);
    }

    public function test_retention_page_calculates_retention_change(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $stats = $component->get('summaryStats');
        // Change from 76.7 to 83.3 = +6.6
        $this->assertEquals(6.6, $stats['retention_change']);
    }

    public function test_retention_page_has_months_back_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $options = $component->invade()->getMonthsBackOptions();
        $this->assertArrayHasKey(6, $options);
        $this->assertArrayHasKey(12, $options);
        $this->assertArrayHasKey(18, $options);
        $this->assertArrayHasKey(24, $options);
    }

    public function test_retention_page_has_period_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $options = $component->invade()->getPeriodOptions();
        $this->assertArrayHasKey('monthly', $options);
        $this->assertArrayHasKey('quarterly', $options);
    }

    public function test_retention_page_builds_chart_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $chartData = $component->get('chartData');
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(5, $chartData['labels']);
        $this->assertCount(2, $chartData['datasets']); // retention + churn
    }

    public function test_retention_page_trend_icon(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $icon = $component->invade()->getTrendIcon();
        $this->assertContains($icon, ['↑', '↓', '→']);
    }

    public function test_retention_page_trend_color_class(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $colorClass = $component->invade()->getTrendColorClass();
        $this->assertMatchesRegularExpression('/text-(green|red|gray)-/', $colorClass);
    }

    public function test_retention_page_retention_color_classes(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        // High retention: green
        $this->assertStringContainsString('green', $component->invade()->getRetentionColorClass(85.0));

        // Good retention: emerald
        $this->assertStringContainsString('emerald', $component->invade()->getRetentionColorClass(65.0));

        // Medium retention: yellow
        $this->assertStringContainsString('yellow', $component->invade()->getRetentionColorClass(45.0));

        // Low retention: orange
        $this->assertStringContainsString('orange', $component->invade()->getRetentionColorClass(25.0));

        // Very low retention: red
        $this->assertStringContainsString('red', $component->invade()->getRetentionColorClass(15.0));
    }

    public function test_retention_page_churn_color_classes(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        // Low churn: green (good)
        $this->assertStringContainsString('green', $component->invade()->getChurnColorClass(15.0));

        // High churn: red (bad)
        $this->assertStringContainsString('red', $component->invade()->getChurnColorClass(85.0));
    }

    public function test_retention_page_format_number(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('500', $component->invade()->formatNumber(500));
        $this->assertEquals('1.5K', $component->invade()->formatNumber(1500));
        $this->assertEquals('1.2M', $component->invade()->formatNumber(1200000));
    }

    public function test_retention_page_period_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Retention::class, ['brandId' => $this->brand->id])
            ->set('period', 'quarterly')
            ->assertSet('period', 'quarterly')
            ->assertSet('loading', false);
    }

    public function test_retention_page_months_back_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Retention::class, ['brandId' => $this->brand->id])
            ->set('monthsBack', 6)
            ->assertSet('monthsBack', 6)
            ->assertSet('loading', false);
    }

    public function test_retention_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->premiumUser);

        Livewire::test(Retention::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_retention_page_admin_can_access(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(Retention::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_retention_page_change_icon(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $icon = $component->invade()->getChangeIcon();
        // Change is positive (6.6), so should be up arrow
        $this->assertEquals('↑', $icon);
    }

    public function test_retention_page_change_color_class(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Retention::class, ['brandId' => $this->brand->id]);

        $colorClass = $component->invade()->getChangeColorClass();
        // Change is positive, so should be green
        $this->assertStringContainsString('green', $colorClass);
    }

    // =====================================================
    // Product Deep Dive Page Tests (D-019)
    // =====================================================

    public function test_product_deep_dive_page_loads_for_premium_supplier(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_product_deep_dive_page_loads_available_products(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        $products = $component->get('availableProducts');
        $this->assertCount(3, $products);
        $this->assertEquals('SKU001', $products[0]['sku']);
        $this->assertEquals('Premium Vitamin C', $products[0]['name']);
    }

    public function test_product_deep_dive_page_loads_product_data_when_sku_selected(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001'])
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_product_deep_dive_page_has_product_info(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $productInfo = $component->get('productInfo');
        $this->assertEquals('SKU001', $productInfo['sku']);
        $this->assertEquals('Premium Vitamin C', $productInfo['name']);
        $this->assertEquals('Health & Wellness', $productInfo['category']);
        $this->assertEquals('Vitamins', $productInfo['subcategory']);
    }

    public function test_product_deep_dive_page_has_performance_metrics(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $performance = $component->get('performanceMetrics');
        $this->assertEquals(125000.0, $performance['total_revenue']);
        $this->assertEquals(2500, $performance['total_units']);
        $this->assertEquals(1800, $performance['total_orders']);
        $this->assertEquals(50.0, $performance['avg_price']);
        $this->assertEquals(69.44, $performance['avg_order_value']);
    }

    public function test_product_deep_dive_page_has_customer_metrics(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $customer = $component->get('customerMetrics');
        $this->assertEquals(1200, $customer['unique_customers']);
        $this->assertEquals(2.1, $customer['avg_qty_per_customer']);
        $this->assertEquals(35.5, $customer['reorder_rate']);
        $this->assertEquals(45, $customer['avg_customer_span_days']);
    }

    public function test_product_deep_dive_page_has_price_metrics(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $price = $component->get('priceMetrics');
        $this->assertEquals(50.0, $price['avg_price']);
        $this->assertEquals(42.0, $price['min_price']);
        $this->assertEquals(55.0, $price['max_price']);
        $this->assertEquals(22.5, $price['promo_rate']);
        $this->assertEquals(8.0, $price['avg_discount']);
    }

    public function test_product_deep_dive_page_has_trend_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $trend = $component->get('trendData');
        $this->assertCount(6, $trend);
        $this->assertEquals('2024-01', $trend[0]['month']);
        $this->assertEquals(18000.0, $trend[0]['revenue']);
    }

    public function test_product_deep_dive_page_has_comparison_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $comparison = $component->get('comparisonData');
        $this->assertEquals(45.2, $comparison['revenue_vs_avg']);
        $this->assertEquals(38.5, $comparison['orders_vs_avg']);
        $this->assertEquals(52.0, $comparison['units_vs_avg']);
    }

    public function test_product_deep_dive_page_builds_chart_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $chartData = $component->get('chartData');
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(6, $chartData['labels']);
        $this->assertCount(3, $chartData['datasets']); // Revenue, Orders, Units
    }

    public function test_product_deep_dive_page_has_months_back_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        $options = $component->invade()->getMonthsBackOptions();
        $this->assertArrayHasKey(6, $options);
        $this->assertArrayHasKey(12, $options);
        $this->assertArrayHasKey(18, $options);
        $this->assertArrayHasKey(24, $options);
    }

    public function test_product_deep_dive_page_format_currency(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('R125.0K', $component->invade()->formatCurrency(125000));
        $this->assertEquals('R1.5M', $component->invade()->formatCurrency(1500000));
        $this->assertEquals('R500', $component->invade()->formatCurrency(500));
    }

    public function test_product_deep_dive_page_format_number(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('2.5K', $component->invade()->formatNumber(2500));
        $this->assertEquals('1.5M', $component->invade()->formatNumber(1500000));
        $this->assertEquals('500', $component->invade()->formatNumber(500));
    }

    public function test_product_deep_dive_page_format_percent(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('35.5%', $component->invade()->formatPercent(35.5));
        $this->assertEquals('100.0%', $component->invade()->formatPercent(100.0));
    }

    public function test_product_deep_dive_page_comparison_color_classes(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        // Positive above 10 - green
        $this->assertStringContainsString('green', $component->invade()->getComparisonColorClass(15.0));
        // Positive 0-10 - emerald
        $this->assertStringContainsString('emerald', $component->invade()->getComparisonColorClass(5.0));
        // Negative 0 to -10 - yellow
        $this->assertStringContainsString('yellow', $component->invade()->getComparisonColorClass(-5.0));
        // Negative below -10 - red
        $this->assertStringContainsString('red', $component->invade()->getComparisonColorClass(-15.0));
    }

    public function test_product_deep_dive_page_comparison_icons(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('↑', $component->invade()->getComparisonIcon(10.0));
        $this->assertEquals('↓', $component->invade()->getComparisonIcon(-10.0));
        $this->assertEquals('→', $component->invade()->getComparisonIcon(0.0));
    }

    public function test_product_deep_dive_page_reorder_rate_color_classes(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        // High reorder rate - green
        $this->assertStringContainsString('green', $component->invade()->getReorderRateColorClass(45.0));
        // Medium - emerald
        $this->assertStringContainsString('emerald', $component->invade()->getReorderRateColorClass(30.0));
        // Low-medium - yellow
        $this->assertStringContainsString('yellow', $component->invade()->getReorderRateColorClass(18.0));
        // Low - red
        $this->assertStringContainsString('red', $component->invade()->getReorderRateColorClass(10.0));
    }

    public function test_product_deep_dive_page_promo_intensity_color_classes(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        // Low promo intensity - good (green)
        $this->assertStringContainsString('green', $component->invade()->getPromoIntensityColorClass(15.0));
        // Medium promo - yellow
        $this->assertStringContainsString('yellow', $component->invade()->getPromoIntensityColorClass(35.0));
        // High promo - orange
        $this->assertStringContainsString('orange', $component->invade()->getPromoIntensityColorClass(55.0));
        // Very high promo - red
        $this->assertStringContainsString('red', $component->invade()->getPromoIntensityColorClass(75.0));
    }

    public function test_product_deep_dive_page_sku_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id])
            ->set('sku', 'SKU001')
            ->assertSet('sku', 'SKU001')
            ->assertSet('loading', false);
    }

    public function test_product_deep_dive_page_months_back_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001'])
            ->set('monthsBack', 6)
            ->assertSet('monthsBack', 6)
            ->assertSet('loading', false);
    }

    public function test_product_deep_dive_page_has_product_data_check(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001']);

        $hasData = $component->invade()->hasProductData();
        $this->assertTrue($hasData);
    }

    public function test_product_deep_dive_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->premiumUser);

        Livewire::test(ProductDeepDive::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_product_deep_dive_page_admin_can_access(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id, 'sku' => 'SKU001'])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_product_deep_dive_page_without_sku_shows_empty_data(): void
    {
        $this->actingAs($this->premiumUser);

        // When no SKU is selected, product data should be empty
        $component = Livewire::test(ProductDeepDive::class, ['brandId' => $this->brand->id]);

        $this->assertFalse($component->invade()->hasProductData());
        $this->assertEmpty($component->get('productInfo'));
        $this->assertEmpty($component->get('performanceMetrics'));
    }

    // =====================================================
    // Marketing Page Tests (D-020)
    // =====================================================

    public function test_marketing_page_loads_for_premium_supplier(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Marketing::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_marketing_page_loads_summary_stats(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $summary = $component->get('summaryStats');
        $this->assertArrayHasKey('total_revenue', $summary);
        $this->assertArrayHasKey('promo_revenue', $summary);
        $this->assertArrayHasKey('regular_revenue', $summary);
        $this->assertArrayHasKey('promo_revenue_pct', $summary);
        $this->assertArrayHasKey('total_discount_given', $summary);
        $this->assertArrayHasKey('avg_discount_pct', $summary);
    }

    public function test_marketing_page_loads_campaign_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $campaigns = $component->get('campaigns');
        $this->assertCount(4, $campaigns);
        $this->assertEquals('0-10%', $campaigns[0]['discount_tier']);
        $this->assertArrayHasKey('revenue', $campaigns[0]);
        $this->assertArrayHasKey('orders', $campaigns[0]);
        $this->assertArrayHasKey('effective_discount_pct', $campaigns[0]);
    }

    public function test_marketing_page_loads_discount_analysis(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $discountAnalysis = $component->get('discountAnalysis');
        $this->assertArrayHasKey('promo', $discountAnalysis);
        $this->assertArrayHasKey('regular', $discountAnalysis);
        $this->assertArrayHasKey('avg_order_value', $discountAnalysis['promo']);
        $this->assertArrayHasKey('unique_customers', $discountAnalysis['regular']);
    }

    public function test_marketing_page_loads_monthly_trend(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $trend = $component->get('monthlyTrend');
        $this->assertCount(3, $trend);
        $this->assertArrayHasKey('month', $trend[0]);
        $this->assertArrayHasKey('promo_revenue', $trend[0]);
        $this->assertArrayHasKey('regular_revenue', $trend[0]);
        $this->assertArrayHasKey('promo_orders', $trend[0]);
        $this->assertArrayHasKey('regular_orders', $trend[0]);
    }

    public function test_marketing_page_builds_chart_data(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $chartData = $component->get('chartData');
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('revenue', $chartData);
        $this->assertArrayHasKey('orders', $chartData);
        $this->assertCount(3, $chartData['labels']);
    }

    public function test_marketing_page_has_months_back_options(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $options = $component->invade()->getMonthsBackOptions();
        $this->assertArrayHasKey(3, $options);
        $this->assertArrayHasKey(6, $options);
        $this->assertArrayHasKey(12, $options);
        $this->assertArrayHasKey(24, $options);
    }

    public function test_marketing_page_months_back_change_triggers_reload(): void
    {
        $this->actingAs($this->premiumUser);

        Livewire::test(Marketing::class, ['brandId' => $this->brand->id])
            ->set('monthsBack', 6)
            ->assertSet('monthsBack', 6)
            ->assertSet('loading', false);
    }

    public function test_marketing_page_format_currency(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('R500', $component->invade()->formatCurrency(500));
        $this->assertEquals('R1.5K', $component->invade()->formatCurrency(1500));
        $this->assertEquals('R1.2M', $component->invade()->formatCurrency(1200000));
    }

    public function test_marketing_page_format_percent(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('15.5%', $component->invade()->formatPercent(15.5));
        $this->assertEquals('0.0%', $component->invade()->formatPercent(0));
    }

    public function test_marketing_page_format_number(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('500', $component->invade()->formatNumber(500));
        $this->assertEquals('1.5K', $component->invade()->formatNumber(1500));
        $this->assertEquals('1.2M', $component->invade()->formatNumber(1200000));
    }

    public function test_marketing_page_promo_intensity_color_class(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        // Low promo intensity - green
        $this->assertStringContainsString('green', $component->invade()->getPromoIntensityColorClass(15.0));
        // Medium promo - yellow
        $this->assertStringContainsString('yellow', $component->invade()->getPromoIntensityColorClass(35.0));
        // High promo - orange
        $this->assertStringContainsString('orange', $component->invade()->getPromoIntensityColorClass(55.0));
        // Very high promo - red
        $this->assertStringContainsString('red', $component->invade()->getPromoIntensityColorClass(75.0));
    }

    public function test_marketing_page_discount_tier_color(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $this->assertEquals('bg-green-500', $component->invade()->getDiscountTierColor('0-10%'));
        $this->assertEquals('bg-emerald-500', $component->invade()->getDiscountTierColor('10-20%'));
        $this->assertEquals('bg-yellow-500', $component->invade()->getDiscountTierColor('20-30%'));
        $this->assertEquals('bg-orange-500', $component->invade()->getDiscountTierColor('30-50%'));
        $this->assertEquals('bg-red-500', $component->invade()->getDiscountTierColor('50%+'));
    }

    public function test_marketing_page_has_campaign_data_check(): void
    {
        $this->actingAs($this->premiumUser);

        $component = Livewire::test(Marketing::class, ['brandId' => $this->brand->id]);

        $this->assertTrue($component->invade()->hasCampaignData());
    }

    public function test_marketing_page_denies_unauthorized_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->premiumUser);

        Livewire::test(Marketing::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.');
    }

    public function test_marketing_page_admin_can_access(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(Marketing::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    // =====================================================
    // Pet Heaven Subscriptions Overview Tests (D-021)
    // =====================================================

    public function test_subscriptions_overview_loads_for_pet_heaven_premium(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSet('brandId', $this->petHeavenBrand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_subscriptions_overview_loads_summary_data(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id]);

        $summary = $component->get('summary');
        $this->assertEquals(500, $summary['total_subscriptions']);
        $this->assertEquals(400, $summary['active_subscriptions']);
        $this->assertEquals(100000.0, $summary['mrr']);
        $this->assertEquals(1200000.0, $summary['arr']);
    }

    public function test_subscriptions_overview_loads_monthly_trend(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id]);

        $monthly = $component->get('monthlyTrend');
        $this->assertCount(3, $monthly);
        $this->assertEquals('2024-01', $monthly[0]['month']);
    }

    public function test_subscriptions_overview_loads_frequency_breakdown(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id]);

        $frequency = $component->get('byFrequency');
        $this->assertCount(3, $frequency);
        $this->assertEquals('Monthly', $frequency[0]['frequency']);
    }

    public function test_subscriptions_overview_format_currency(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertEquals('R500', $component->invade()->formatCurrency(500));
        $this->assertEquals('R1.5K', $component->invade()->formatCurrency(1500));
        $this->assertEquals('R1.2M', $component->invade()->formatCurrency(1200000));
    }

    public function test_subscriptions_overview_format_percent(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertEquals('15.5%', $component->invade()->formatPercent(15.5));
        $this->assertEquals('0.0%', $component->invade()->formatPercent(0));
    }

    public function test_subscriptions_overview_churn_color_class(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertStringContainsString('green', $component->invade()->getChurnColorClass(3.0));
        $this->assertStringContainsString('yellow', $component->invade()->getChurnColorClass(8.0));
        $this->assertStringContainsString('orange', $component->invade()->getChurnColorClass(15.0));
        $this->assertStringContainsString('red', $component->invade()->getChurnColorClass(25.0));
    }

    public function test_subscriptions_overview_denies_non_pet_heaven_brand(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        // Create a non-Pet Heaven brand (company_id = 3 is FtN)
        $nonPetHeavenBrand = Brand::factory()->create(['name' => 'Non PH Brand', 'company_id' => 3]);
        $this->petHeavenPremiumUser->brands()->attach($nonPetHeavenBrand);

        Livewire::test(SubscriptionsOverview::class, ['brandId' => $nonPetHeavenBrand->id])
            ->assertSet('error', 'Subscription data is only available for Pet Heaven brands.');
    }

    public function test_subscriptions_overview_admin_can_access(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->adminUser);

        Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_subscriptions_overview_has_subscription_data_check(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionsOverview::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertTrue($component->invade()->hasSubscriptionData());
    }

    // =====================================================
    // Pet Heaven Subscription Products Tests (D-021)
    // =====================================================

    public function test_subscription_products_loads_for_pet_heaven_premium(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSet('brandId', $this->petHeavenBrand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_subscription_products_loads_product_data(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id]);

        $products = $component->get('products');
        $this->assertCount(2, $products);
        $this->assertEquals('Premium Dog Food', $products[0]['product_name']);
    }

    public function test_subscription_products_calculates_totals(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id]);

        $totals = $component->get('totals');
        $this->assertArrayHasKey('active_subscriptions', $totals);
        $this->assertArrayHasKey('mrr', $totals);
        $this->assertArrayHasKey('subscribers', $totals);
    }

    public function test_subscription_products_sorting(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id]);

        // Default sort is by active_subscriptions desc
        $component->assertSet('sortColumn', 'active_subscriptions')
            ->assertSet('sortDirection', 'desc');

        // Change sort to mrr
        $component->call('sortBy', 'mrr')
            ->assertSet('sortColumn', 'mrr')
            ->assertSet('sortDirection', 'desc');

        // Clicking same column toggles direction
        $component->call('sortBy', 'mrr')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_subscription_products_sort_icon(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id]);

        // Sort column shows directional arrow
        $this->assertEquals('↓', $component->invade()->getSortIcon('active_subscriptions'));
        // Non-sort column shows bidirectional arrow
        $this->assertEquals('↕', $component->invade()->getSortIcon('mrr'));
    }

    public function test_subscription_products_format_percent(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertEquals('16.7%', $component->invade()->formatPercent(16.7));
    }

    public function test_subscription_products_churn_color_class(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertStringContainsString('green', $component->invade()->getChurnColorClass(4.0));
        $this->assertStringContainsString('yellow', $component->invade()->getChurnColorClass(8.0));
        $this->assertStringContainsString('orange', $component->invade()->getChurnColorClass(15.0));
        $this->assertStringContainsString('red', $component->invade()->getChurnColorClass(25.0));
    }

    public function test_subscription_products_months_back_options(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id]);

        $options = $component->invade()->getMonthsBackOptions();
        $this->assertArrayHasKey(3, $options);
        $this->assertArrayHasKey(6, $options);
        $this->assertArrayHasKey(12, $options);
        $this->assertArrayHasKey(24, $options);
    }

    public function test_subscription_products_denies_non_pet_heaven_brand(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        // Give access to a non-Pet Heaven brand
        $this->petHeavenPremiumUser->brands()->attach($this->brand);

        Livewire::test(SubscriptionProducts::class, ['brandId' => $this->brand->id])
            ->assertSet('error', 'Subscription data is only available for Pet Heaven brands.');
    }

    public function test_subscription_products_admin_can_access(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->adminUser);

        Livewire::test(SubscriptionProducts::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    // =====================================================
    // Pet Heaven Subscription Predictions Tests (D-021)
    // =====================================================

    public function test_subscription_predictions_loads_for_pet_heaven_premium(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSet('brandId', $this->petHeavenBrand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_subscription_predictions_loads_upcoming_deliveries(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $upcoming = $component->get('upcoming');
        $this->assertCount(2, $upcoming);
        $this->assertEquals('John Doe', $upcoming[0]['customer_name']);
        $this->assertEquals('Premium Dog Food', $upcoming[0]['product_name']);
    }

    public function test_subscription_predictions_loads_at_risk(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $atRisk = $component->get('atRisk');
        $this->assertCount(1, $atRisk);
        $this->assertEquals('Bob Wilson', $atRisk[0]['customer_name']);
        $this->assertEquals('Overdue', $atRisk[0]['risk_reason']);
    }

    public function test_subscription_predictions_loads_summary(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $summary = $component->get('summary');
        $this->assertEquals(45, $summary['deliveries_next_7_days']);
        $this->assertEquals(180, $summary['deliveries_next_30_days']);
        $this->assertEquals(1, $summary['at_risk_count']);
    }

    public function test_subscription_predictions_format_date(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertEquals('Jul 10, 2024', $component->invade()->formatDate('2024-07-10'));
        $this->assertEquals('-', $component->invade()->formatDate(null));
    }

    public function test_subscription_predictions_urgency_color_class(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        // 3 days or less - urgent (red)
        $this->assertStringContainsString('red', $component->invade()->getUrgencyColorClass(2));
        // 4-7 days - warning (yellow)
        $this->assertStringContainsString('yellow', $component->invade()->getUrgencyColorClass(5));
        // More than 7 days - normal (blue)
        $this->assertStringContainsString('blue', $component->invade()->getUrgencyColorClass(10));
    }

    public function test_subscription_predictions_risk_reason_class(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertStringContainsString('red', $component->invade()->getRiskReasonClass('Overdue'));
        $this->assertStringContainsString('orange', $component->invade()->getRiskReasonClass('Multiple Skips'));
        $this->assertStringContainsString('yellow', $component->invade()->getRiskReasonClass('Low Engagement'));
        $this->assertStringContainsString('gray', $component->invade()->getRiskReasonClass('Other'));
    }

    public function test_subscription_predictions_has_upcoming_check(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertTrue($component->invade()->hasUpcoming());
    }

    public function test_subscription_predictions_has_at_risk_check(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertTrue($component->invade()->hasAtRisk());
    }

    public function test_subscription_predictions_has_data_check(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        $component = Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id]);

        $this->assertTrue($component->invade()->hasData());
    }

    public function test_subscription_predictions_denies_non_pet_heaven_brand(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->petHeavenPremiumUser);

        // Give access to a non-Pet Heaven brand
        $this->petHeavenPremiumUser->brands()->attach($this->brand);

        Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->brand->id])
            ->assertSet('error', 'Subscription predictions are only available for Pet Heaven brands.');
    }

    public function test_subscription_predictions_admin_can_access(): void
    {
        $this->setUpPetHeavenDeployment();
        $this->actingAs($this->adminUser);

        Livewire::test(SubscriptionPredictions::class, ['brandId' => $this->petHeavenBrand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }
}
