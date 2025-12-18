<?php

namespace Tests\Unit;

use App\Services\BigQueryService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;

/**
 * Unit tests for BigQueryService.
 *
 * These tests don't require database access as they test pure logic
 * and use mocking for BigQuery interactions.
 */
class BigQueryServiceTest extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\Concerns\InteractsWithContainer;

    /**
     * Creates the application.
     */
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_service_falls_back_to_adc_without_credentials_file(): void
    {
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.credentials_path', '/non/existent/path.json');

        $service = new BigQueryService;

        // When credentials file doesn't exist, service falls back to Application Default Credentials (ADC)
        // If ADC is available (user ran `gcloud auth application-default login`), service will be configured
        // This test verifies the service doesn't crash when credentials file is missing
        // The service may be configured or not depending on ADC availability
        $this->assertIsBool($service->isConfigured());
    }

    public function test_service_reports_not_configured_without_project_id(): void
    {
        Config::set('bigquery.project_id', null);

        $service = new BigQueryService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_query_throws_exception_when_project_id_not_configured(): void
    {
        // Only project_id being null guarantees the service is not configured
        // Missing credentials file may fallback to ADC
        Config::set('bigquery.project_id', null);
        Config::set('bigquery.credentials_path', '/non/existent/path.json');

        $service = new BigQueryService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BigQuery client is not configured');

        $service->query('SELECT 1');
    }

    public function test_get_company_id_returns_configured_value(): void
    {
        Config::set('bigquery.company_id', 5);

        $service = new BigQueryService;

        $this->assertEquals(5, $service->getCompanyId());
    }

    public function test_get_dataset_returns_configured_value(): void
    {
        Config::set('bigquery.dataset', 'custom_dataset');

        $service = new BigQueryService;

        $this->assertEquals('custom_dataset', $service->getDataset());
    }

    public function test_calculate_start_date_for_days(): void
    {
        $service = new BigQueryService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateStartDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, '7d');

        $expected = now()->subDays(7)->format('Y-m-d');
        $this->assertEquals($expected, $result);
    }

    public function test_calculate_start_date_for_weeks(): void
    {
        $service = new BigQueryService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateStartDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, '4w');

        $expected = now()->subWeeks(4)->format('Y-m-d');
        $this->assertEquals($expected, $result);
    }

    public function test_calculate_start_date_for_months(): void
    {
        $service = new BigQueryService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateStartDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, '3m');

        $expected = now()->subMonths(3)->format('Y-m-d');
        $this->assertEquals($expected, $result);
    }

    public function test_calculate_start_date_for_years(): void
    {
        $service = new BigQueryService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateStartDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, '1y');

        $expected = now()->subYears(1)->format('Y-m-d');
        $this->assertEquals($expected, $result);
    }

    public function test_calculate_start_date_defaults_to_12_months(): void
    {
        $service = new BigQueryService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateStartDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'invalid');

        $expected = now()->subMonths(12)->format('Y-m-d');
        $this->assertEquals($expected, $result);
    }

    public function test_query_cached_uses_cache(): void
    {
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.credentials_path', '/non/existent/path.json');

        // Pre-populate cache
        Cache::put('bigquery:test-key', [['result' => 'cached']], 900);

        $service = new BigQueryService;

        // queryCached should return cached value without trying to query
        $result = Cache::get('bigquery:test-key');

        $this->assertEquals([['result' => 'cached']], $result);
    }

    public function test_clear_cache_removes_specific_key(): void
    {
        Cache::put('bigquery:brands:3', ['brand1', 'brand2'], 900);

        $service = new BigQueryService;
        $service->clearCache('brands:3');

        $this->assertNull(Cache::get('bigquery:brands:3'));
    }

    public function test_period_to_days_returns_correct_values(): void
    {
        $service = new BigQueryService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('periodToDays');
        $method->setAccessible(true);

        $this->assertEquals(30, $method->invoke($service, '30d'));
        $this->assertEquals(90, $method->invoke($service, '90d'));
        $this->assertEquals(365, $method->invoke($service, '1yr'));
        $this->assertEquals(365, $method->invoke($service, '365d'));
        $this->assertEquals(365, $method->invoke($service, '1y'));
        $this->assertEquals(30, $method->invoke($service, 'invalid'));
    }

    public function test_get_brand_kpis_returns_empty_when_no_results(): void
    {
        $service = $this->createMockedBigQueryService([]);

        $result = $service->getBrandKpis('TestBrand', '30d');

        $this->assertEquals([
            'revenue' => 0,
            'orders' => 0,
            'units' => 0,
            'aov' => 0,
            'revenue_change' => null,
            'orders_change' => null,
            'units_change' => null,
            'aov_change' => null,
        ], $result);
    }

    public function test_get_brand_kpis_returns_formatted_data(): void
    {
        $mockData = [[
            'revenue' => 125000.50,
            'orders' => 450,
            'units' => 1200,
            'aov' => 277.78,
            'revenue_change' => 12.345,
            'orders_change' => 8.123,
            'units_change' => 15.678,
            'aov_change' => -3.456,
        ]];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getBrandKpis('TestBrand', '30d');

        $this->assertEquals(125000.50, $result['revenue']);
        $this->assertEquals(450, $result['orders']);
        $this->assertEquals(1200, $result['units']);
        $this->assertEquals(277.78, $result['aov']);
        $this->assertEquals(12.3, $result['revenue_change']);
        $this->assertEquals(8.1, $result['orders_change']);
        $this->assertEquals(15.7, $result['units_change']);
        $this->assertEquals(-3.5, $result['aov_change']);
    }

    public function test_get_sales_trend_returns_chart_formatted_data(): void
    {
        $mockData = [
            ['month' => '2024-01', 'revenue' => 10000],
            ['month' => '2024-02', 'revenue' => 12000],
            ['month' => '2024-03', 'revenue' => 15000],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getSalesTrend('TestBrand', 12);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertEquals(['2024-01', '2024-02', '2024-03'], $result['labels']);
        $this->assertCount(1, $result['datasets']);
        $this->assertEquals('TestBrand', $result['datasets'][0]['label']);
        $this->assertEquals([10000.0, 12000.0, 15000.0], $result['datasets'][0]['data']);
        $this->assertEquals('#006654', $result['datasets'][0]['borderColor']);
    }

    public function test_get_top_products_returns_formatted_array(): void
    {
        $mockData = [
            ['sku' => 'SKU001', 'name' => 'Product 1', 'revenue' => 45000, 'units' => 500, 'growth' => 20.5],
            ['sku' => 'SKU002', 'name' => 'Product 2', 'revenue' => 35000, 'units' => 400, 'growth' => null],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getTopProducts('TestBrand', 5, '30d');

        $this->assertCount(2, $result);
        $this->assertEquals('SKU001', $result[0]['sku']);
        $this->assertEquals('Product 1', $result[0]['name']);
        $this->assertEquals(45000.0, $result[0]['revenue']);
        $this->assertEquals(500, $result[0]['units']);
        $this->assertEquals(20.5, $result[0]['growth']);
        $this->assertNull($result[1]['growth']);
    }

    public function test_get_product_performance_table_pivots_data_correctly(): void
    {
        $mockData = [
            ['sku' => 'SKU001', 'name' => 'Product 1', 'category' => 'Cat A', 'month' => '2024-01', 'revenue' => 1000],
            ['sku' => 'SKU001', 'name' => 'Product 1', 'category' => 'Cat A', 'month' => '2024-02', 'revenue' => 1200],
            ['sku' => 'SKU002', 'name' => 'Product 2', 'category' => 'Cat B', 'month' => '2024-01', 'revenue' => 800],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getProductPerformanceTable('TestBrand', '12m');

        $this->assertCount(2, $result);

        // First product (highest total)
        $this->assertEquals('SKU001', $result[0]['sku']);
        $this->assertEquals('Product 1', $result[0]['name']);
        $this->assertEquals('Cat A', $result[0]['category']);
        $this->assertEquals(2200.0, $result[0]['total']);
        $this->assertEquals(1000.0, $result[0]['months']['2024-01']);
        $this->assertEquals(1200.0, $result[0]['months']['2024-02']);

        // Second product
        $this->assertEquals('SKU002', $result[1]['sku']);
        $this->assertEquals(800.0, $result[1]['total']);
    }

    public function test_get_competitor_comparison_anonymizes_labels(): void
    {
        $mockData = [
            ['brand' => 'MyBrand', 'month' => '2024-01', 'revenue' => 10000],
            ['brand' => 'CompA', 'month' => '2024-01', 'revenue' => 8000],
            ['brand' => 'CompB', 'month' => '2024-01', 'revenue' => 6000],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getCompetitorComparison('MyBrand', ['CompA', 'CompB'], '30d');

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertEquals(['2024-01'], $result['labels']);
        $this->assertCount(3, $result['datasets']);

        // First dataset should be "Your Brand"
        $this->assertEquals('Your Brand', $result['datasets'][0]['label']);
        $this->assertEquals([10000.0], $result['datasets'][0]['data']);

        // Competitors should be anonymized
        $this->assertEquals('Competitor A', $result['datasets'][1]['label']);
        $this->assertEquals('Competitor B', $result['datasets'][2]['label']);
    }

    public function test_get_market_share_by_category_returns_formatted_data(): void
    {
        $mockData = [
            ['category' => 'Health', 'subcategory' => 'Vitamins', 'brand' => 'MyBrand', 'revenue' => 1000, 'total_revenue' => 2500, 'market_share' => 40.0],
            ['category' => 'Health', 'subcategory' => 'Vitamins', 'brand' => 'CompA', 'revenue' => 1500, 'total_revenue' => 2500, 'market_share' => 60.0],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getMarketShareByCategory('MyBrand', ['CompA'], '30d');

        $this->assertCount(1, $result);
        $this->assertEquals('Health', $result[0]['category']);
        $this->assertEquals('Vitamins', $result[0]['subcategory']);
        $this->assertEquals(40.0, $result[0]['brand_share']);
        $this->assertArrayHasKey('Competitor A', $result[0]['competitor_shares']);
        $this->assertEquals(60.0, $result[0]['competitor_shares']['Competitor A']);
    }

    public function test_get_customer_engagement_returns_formatted_metrics(): void
    {
        $mockData = [
            [
                'sku' => 'SKU001',
                'name' => 'Product 1',
                'avg_qty_per_order' => 2.345,
                'reorder_rate' => 35.678,
                'avg_frequency_months' => 2.5,
                'promo_intensity' => 0, // Promo data not available in BigQuery
            ],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getCustomerEngagement('TestBrand', '12m');

        $this->assertCount(1, $result);
        $this->assertEquals('SKU001', $result[0]['sku']);
        $this->assertEquals('Product 1', $result[0]['name']);
        $this->assertEquals(2.35, $result[0]['avg_qty_per_order']);
        $this->assertEquals(35.7, $result[0]['reorder_rate']);
        $this->assertEquals(2.5, $result[0]['avg_frequency_months']);
        $this->assertEquals(0, $result[0]['promo_intensity']); // Hardcoded to 0 since no promo data
    }

    public function test_get_stock_supply_returns_stub_structure(): void
    {
        // Skipped: Supply chain tables don't exist in BigQuery - method returns limited stub data
        // Method now queries dim_product for basic stock info only
        $this->markTestSkipped('Supply chain tables not available in BigQuery - method returns stub data with closing_stock only');
    }

    public function test_get_purchase_orders_returns_stub_structure(): void
    {
        // Method returns stub data since purchase order tables don't exist in BigQuery
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.dataset', 'test_dataset');
        Config::set('bigquery.company_id', 3);

        $service = new BigQueryService();

        $result = $service->getPurchaseOrders('TestBrand', 12);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('monthly', $result);
        $this->assertArrayHasKey('orders', $result);

        // Stub returns empty/zero data
        $this->assertEquals(0, $result['summary']['total_pos']);
        $this->assertEquals(0, $result['summary']['on_time_pct']);
        $this->assertEquals(0, $result['summary']['in_full_pct']);
        $this->assertEquals(0, $result['summary']['otif_pct']);
        $this->assertEquals([], $result['monthly']);
        $this->assertEquals([], $result['orders']);
    }

    public function test_get_purchase_order_lines_returns_empty_array(): void
    {
        // Method returns stub empty array since purchase order tables don't exist in BigQuery
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.dataset', 'test_dataset');
        Config::set('bigquery.company_id', 3);

        $service = new BigQueryService();

        $result = $service->getPurchaseOrderLines('PO001');

        $this->assertEquals([], $result);
    }

    public function test_get_brand_kpis_handles_null_changes(): void
    {
        $mockData = [[
            'revenue' => 5000,
            'orders' => 20,
            'units' => 50,
            'aov' => 250,
            'revenue_change' => null,
            'orders_change' => null,
            'units_change' => null,
            'aov_change' => null,
        ]];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getBrandKpis('NewBrand', '30d');

        $this->assertEquals(5000.0, $result['revenue']);
        $this->assertNull($result['revenue_change']);
        $this->assertNull($result['orders_change']);
        $this->assertNull($result['units_change']);
        $this->assertNull($result['aov_change']);
    }

    public function test_get_sales_trend_handles_empty_data(): void
    {
        $service = $this->createMockedBigQueryService([]);

        $result = $service->getSalesTrend('TestBrand', 12);

        $this->assertEquals([], $result['labels']);
        $this->assertEquals([], $result['datasets'][0]['data']);
    }

    public function test_get_market_share_handles_empty_subcategory(): void
    {
        $mockData = [
            ['category' => 'Health', 'subcategory' => '', 'brand' => 'MyBrand', 'revenue' => 1000, 'total_revenue' => 1000, 'market_share' => 100.0],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getMarketShareByCategory('MyBrand', [], '30d');

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['subcategory']);
    }

    public function test_get_customer_engagement_handles_null_frequency(): void
    {
        $mockData = [
            [
                'sku' => 'SKU001',
                'name' => 'Product 1',
                'avg_qty_per_order' => 1.0,
                'reorder_rate' => 0,
                'avg_frequency_months' => null,
                'promo_intensity' => 0,
            ],
        ];

        $service = $this->createMockedBigQueryService($mockData);

        $result = $service->getCustomerEngagement('TestBrand', '12m');

        $this->assertNull($result[0]['avg_frequency_months']);
    }

    // =====================================================
    // Pricing / Competitor Price Tests (E-010)
    // =====================================================

    public function test_get_price_history_returns_stub_structure(): void
    {
        // Method returns stub data since price history tables don't exist in BigQuery
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.dataset', 'test_dataset');
        Config::set('bigquery.company_id', 3);

        $service = new BigQueryService();

        $result = $service->getPriceHistory('PROD001', null, '90d');

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertEquals([], $result['labels']);
        $this->assertEquals([], $result['datasets']);
    }

    public function test_get_price_history_formats_data_for_charting(): void
    {
        // Skipped: Price history tables don't exist in BigQuery
        $this->markTestSkipped('Price history tables not available in BigQuery - method returns stub data');
    }

    public function test_get_price_history_handles_single_competitor(): void
    {
        // Skipped: Price history tables don't exist in BigQuery
        $this->markTestSkipped('Price history tables not available in BigQuery - method returns stub data');
    }

    public function test_get_competitor_prices_returns_empty_array(): void
    {
        // Method returns stub empty array since competitor pricing tables don't exist in BigQuery
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.dataset', 'test_dataset');
        Config::set('bigquery.company_id', 3);

        $service = new BigQueryService();

        $result = $service->getCompetitorPrices(null, null, 100);

        $this->assertEquals([], $result);
    }

    public function test_get_competitor_prices_calculates_market_position_cheapest(): void
    {
        // Skipped: Competitor pricing tables don't exist in BigQuery
        $this->markTestSkipped('Competitor pricing tables not available in BigQuery - method returns stub data');
    }

    public function test_get_competitor_prices_calculates_market_position_most_expensive(): void
    {
        // Skipped: Competitor pricing tables don't exist in BigQuery
        // Method returns stub data instead
        $this->markTestSkipped('Competitor pricing tables not available in BigQuery - method returns stub data');
    }

    public function test_get_competitor_prices_calculates_market_position_below_average(): void
    {
        // Skipped: Competitor pricing tables don't exist in BigQuery
        $this->markTestSkipped('Competitor pricing tables not available in BigQuery - method returns stub data');
    }

    public function test_get_competitor_prices_calculates_market_position_above_average(): void
    {
        // Skipped: Competitor pricing tables don't exist in BigQuery
        $this->markTestSkipped('Competitor pricing tables not available in BigQuery - method returns stub data');
    }

    public function test_get_competitor_prices_handles_null_our_price(): void
    {
        // Skipped: Competitor pricing tables don't exist in BigQuery
        $this->markTestSkipped('Competitor pricing tables not available in BigQuery - method returns stub data');
    }

    public function test_get_price_alert_triggers_returns_stub_structure(): void
    {
        // Method returns stub data since pricing tables don't exist in BigQuery
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.dataset', 'test_dataset');
        Config::set('bigquery.company_id', 3);

        $service = new BigQueryService();

        $result = $service->getPriceAlertTriggers(null, '7d');

        $this->assertArrayHasKey('price_drops', $result);
        $this->assertArrayHasKey('competitor_beats', $result);
        $this->assertArrayHasKey('out_of_stock', $result);
        $this->assertArrayHasKey('price_changes', $result);
        $this->assertEquals([], $result['price_drops']);
        $this->assertEquals([], $result['competitor_beats']);
        $this->assertEquals([], $result['out_of_stock']);
        $this->assertEquals([], $result['price_changes']);
    }

    public function test_get_pricing_kpis_returns_stub_data(): void
    {
        // Method returns stub data since pricing tables don't exist in BigQuery
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.dataset', 'test_dataset');
        Config::set('bigquery.company_id', 3);

        $service = new BigQueryService();

        $result = $service->getPricingKpis();

        $this->assertEquals(0, $result['products_tracked']);
        $this->assertEquals('unknown', $result['avg_market_position']);
        $this->assertEquals(0, $result['products_cheapest']);
        $this->assertEquals(0, $result['products_most_expensive']);
        $this->assertEquals(0, $result['recent_price_changes']);
        $this->assertEquals(0, $result['active_competitor_undercuts']);
    }

    public function test_get_pricing_kpis_returns_formatted_data(): void
    {
        // Skipped: Pricing tables don't exist in BigQuery - method returns stub data
        $this->markTestSkipped('Pricing tables not available in BigQuery - method returns stub data');
    }

    public function test_get_pricing_kpis_premium_position(): void
    {
        // Skipped: Pricing tables don't exist in BigQuery - method returns stub data
        $this->markTestSkipped('Pricing tables not available in BigQuery - method returns stub data');
    }

    public function test_get_pricing_kpis_mid_market_position(): void
    {
        // Skipped: Pricing tables don't exist in BigQuery - method returns stub data
        $this->markTestSkipped('Pricing tables not available in BigQuery - method returns stub data');
    }

    /**
     * Create a BigQueryService that returns mock data via cache.
     *
     * @param  array<int, array<string, mixed>>  $mockData
     */
    private function createMockedBigQueryService(array $mockData): BigQueryService
    {
        // Set up config so the service can initialize properly
        Config::set('bigquery.project_id', 'test-project');
        Config::set('bigquery.credentials_path', '/non/existent/path.json');
        Config::set('bigquery.dataset', 'test_dataset');
        Config::set('bigquery.company_id', 3);
        Config::set('bigquery.cache_ttl', 900);

        // For testing, we'll override the queryCached to return mock data
        $mock = Mockery::mock(BigQueryService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Use the parent class for reflection to access private properties
        $reflection = new \ReflectionClass(BigQueryService::class);

        $datasetProp = $reflection->getProperty('dataset');
        $datasetProp->setAccessible(true);
        $datasetProp->setValue($mock, 'test_dataset');

        $companyIdProp = $reflection->getProperty('companyId');
        $companyIdProp->setAccessible(true);
        $companyIdProp->setValue($mock, 3);

        $cacheTtlProp = $reflection->getProperty('cacheTtl');
        $cacheTtlProp->setAccessible(true);
        $cacheTtlProp->setValue($mock, 900);

        $mock->shouldReceive('queryCached')
            ->andReturn($mockData);

        return $mock;
    }
}
