<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandCompetitor;
use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SupplyChartApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $supplierUser;

    private User $unauthorizedUser;

    private Brand $brand;

    private Brand $competitorBrand;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with roles
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->supplierUser = User::factory()->create();
        $this->supplierUser->assignRole('supplier-basic');

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('pim-editor');

        // Create test brands
        $this->brand = Brand::factory()->create(['name' => 'Test Brand']);
        $this->competitorBrand = Brand::factory()->create(['name' => 'Competitor Brand']);

        // Grant supplier access to brand
        $this->supplierUser->brands()->attach($this->brand->id);

        // Create competitor relationship
        BrandCompetitor::create([
            'brand_id' => $this->brand->id,
            'competitor_brand_id' => $this->competitorBrand->id,
            'position' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_endpoints(): void
    {
        $endpoints = [
            '/api/supply/charts/sales-trend',
            '/api/supply/charts/competitor',
            '/api/supply/charts/market-share',
            '/api/supply/tables/products',
            '/api/supply/tables/stock',
            '/api/supply/tables/purchase-orders',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint.'?brand_id='.$this->brand->id);
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function test_unauthorized_user_cannot_access_supply_endpoints(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->getJson('/api/supply/charts/sales-trend?brand_id='.$this->brand->id);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_cannot_access_brand_they_dont_own(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/sales-trend?brand_id='.$otherBrand->id);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'You do not have access to this brand.',
        ]);
    }

    /** @test */
    public function test_brand_id_is_required(): void
    {
        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/sales-trend');

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'error']);
    }

    /** @test */
    public function test_sales_trend_returns_correct_format(): void
    {
        $mockData = [
            'labels' => ['2025-01', '2025-02', '2025-03'],
            'datasets' => [
                [
                    'label' => 'Test Brand',
                    'data' => [1000.0, 1500.0, 2000.0],
                    'borderColor' => '#006654',
                    'backgroundColor' => 'rgba(0, 102, 84, 0.1)',
                ],
            ],
        ];

        $this->mockBigQueryService('getSalesTrend', $mockData);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/sales-trend?brand_id='.$this->brand->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'labels',
                'datasets',
            ],
            'cached_until',
        ]);
        $response->assertJson([
            'success' => true,
            'data' => $mockData,
        ]);
    }

    /** @test */
    public function test_sales_trend_accepts_months_parameter(): void
    {
        $mockData = ['labels' => [], 'datasets' => []];
        $mockService = $this->mockBigQueryService('getSalesTrend', $mockData);

        $mockService->shouldReceive('getSalesTrend')
            ->with('Test Brand', 6)
            ->once()
            ->andReturn($mockData);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/sales-trend?brand_id='.$this->brand->id.'&months=6');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_competitor_comparison_returns_correct_format(): void
    {
        $mockData = [
            'labels' => ['2025-01', '2025-02'],
            'datasets' => [
                ['label' => 'Your Brand', 'data' => [1000.0, 1500.0], 'borderColor' => '#006654', 'backgroundColor' => '#006654'],
                ['label' => 'Competitor A', 'data' => [900.0, 1200.0], 'borderColor' => '#3B82F6', 'backgroundColor' => '#3B82F6'],
            ],
        ];

        $this->mockBigQueryService('getCompetitorComparison', $mockData);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/supply/charts/competitor?brand_id='.$this->brand->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'labels',
                'datasets',
            ],
            'cached_until',
        ]);
    }

    /** @test */
    public function test_competitor_comparison_accepts_period_parameter(): void
    {
        $mockData = ['labels' => [], 'datasets' => []];
        $this->mockBigQueryService('getCompetitorComparison', $mockData);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/supply/charts/competitor?brand_id='.$this->brand->id.'&period=90d');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_market_share_returns_correct_format(): void
    {
        $mockData = [
            [
                'category' => 'Health & Beauty',
                'subcategory' => 'Skincare',
                'brand_share' => 35.5,
                'competitor_shares' => [
                    'Competitor A' => 25.0,
                    'Competitor B' => 20.0,
                ],
            ],
        ];

        $this->mockBigQueryService('getMarketShareByCategory', $mockData);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/market-share?brand_id='.$this->brand->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'category',
                    'subcategory',
                    'brand_share',
                    'competitor_shares',
                ],
            ],
            'cached_until',
        ]);
    }

    /** @test */
    public function test_products_table_returns_correct_format(): void
    {
        $mockData = [
            [
                'sku' => 'TEST-001',
                'name' => 'Test Product',
                'category' => 'Health',
                'months' => ['2025-01' => 1000.0, '2025-02' => 1500.0],
                'total' => 2500.0,
            ],
        ];

        $this->mockBigQueryService('getProductPerformanceTable', $mockData);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/tables/products?brand_id='.$this->brand->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ],
            'cached_until',
        ]);
    }

    /** @test */
    public function test_products_table_supports_pagination(): void
    {
        $mockData = array_fill(0, 100, [
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'category' => 'Health',
            'months' => [],
            'total' => 1000.0,
        ]);

        $this->mockBigQueryService('getProductPerformanceTable', $mockData);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/tables/products?brand_id='.$this->brand->id.'&page=2&per_page=25');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'pagination' => [
                    'current_page' => 2,
                    'per_page' => 25,
                    'total' => 100,
                    'last_page' => 4,
                ],
            ],
        ]);
    }

    /** @test */
    public function test_stock_table_returns_correct_format(): void
    {
        $mockData = [
            'sell_in' => [
                ['sku' => 'TEST-001', 'name' => 'Test Product', 'months' => ['2025-01' => 100]],
            ],
            'sell_out' => [
                ['sku' => 'TEST-001', 'name' => 'Test Product', 'months' => ['2025-01' => 90]],
            ],
            'closing_stock' => [
                ['sku' => 'TEST-001', 'name' => 'Test Product', 'months' => ['2025-01' => 10]],
            ],
        ];

        $this->mockBigQueryService('getStockSupply', $mockData);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/tables/stock?brand_id='.$this->brand->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'sell_in',
                'sell_out',
                'closing_stock',
            ],
            'cached_until',
        ]);
    }

    /** @test */
    public function test_purchase_orders_table_returns_correct_format(): void
    {
        $mockData = [
            'summary' => [
                'total_pos' => 50,
                'on_time_pct' => 85.5,
                'in_full_pct' => 90.0,
                'otif_pct' => 80.0,
            ],
            'monthly' => [
                ['month' => '2025-01', 'po_count' => 10, 'on_time_pct' => 85.0, 'in_full_pct' => 90.0, 'otif_pct' => 80.0],
            ],
            'orders' => [
                [
                    'po_number' => 'PO-001',
                    'order_date' => '2025-01-15',
                    'expected_delivery_date' => '2025-01-22',
                    'actual_delivery_date' => '2025-01-21',
                    'status' => 'delivered',
                    'line_count' => 5,
                    'total_value' => 5000.0,
                    'delivered_on_time' => true,
                    'delivered_in_full' => true,
                ],
            ],
        ];

        $this->mockBigQueryService('getPurchaseOrders', $mockData);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/tables/purchase-orders?brand_id='.$this->brand->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary',
                'monthly',
                'orders' => [
                    'data',
                    'pagination',
                ],
            ],
            'cached_until',
        ]);
    }

    /** @test */
    public function test_admin_can_access_any_brand(): void
    {
        $otherBrand = Brand::factory()->create(['name' => 'Admin Test Brand']);

        $mockData = ['labels' => [], 'datasets' => []];
        $this->mockBigQueryService('getSalesTrend', $mockData);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/supply/charts/sales-trend?brand_id='.$otherBrand->id);

        $response->assertStatus(200);
    }

    /** @test */
    public function test_invalid_period_returns_validation_error(): void
    {
        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/competitor?brand_id='.$this->brand->id.'&period=invalid');

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_invalid_months_returns_validation_error(): void
    {
        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/sales-trend?brand_id='.$this->brand->id.'&months=999');

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_bigquery_error_returns_proper_json_error(): void
    {
        $mockService = Mockery::mock(BigQueryService::class);
        $mockService->shouldReceive('getSalesTrend')
            ->andThrow(new \RuntimeException('BigQuery timeout'));

        $this->app->instance(BigQueryService::class, $mockService);

        $response = $this->actingAs($this->supplierUser)
            ->getJson('/api/supply/charts/sales-trend?brand_id='.$this->brand->id);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
        ]);
        $response->assertJsonStructure(['success', 'error']);
    }

    /** @test */
    public function test_all_endpoints_return_cached_until_timestamp(): void
    {
        $endpoints = [
            '/api/supply/charts/sales-trend' => 'getSalesTrend',
            '/api/supply/charts/competitor' => 'getCompetitorComparison',
            '/api/supply/charts/market-share' => 'getMarketShareByCategory',
            '/api/supply/tables/products' => 'getProductPerformanceTable',
            '/api/supply/tables/stock' => 'getStockSupply',
            '/api/supply/tables/purchase-orders' => 'getPurchaseOrders',
        ];

        foreach ($endpoints as $endpoint => $method) {
            $mockData = match ($method) {
                'getStockSupply' => ['sell_in' => [], 'sell_out' => [], 'closing_stock' => []],
                'getPurchaseOrders' => ['summary' => [], 'monthly' => [], 'orders' => []],
                'getProductPerformanceTable', 'getMarketShareByCategory' => [],
                default => ['labels' => [], 'datasets' => []],
            };

            $this->mockBigQueryService($method, $mockData);

            $response = $this->actingAs($this->supplierUser)
                ->getJson($endpoint.'?brand_id='.$this->brand->id);

            $response->assertStatus(200);
            $response->assertJsonStructure(['cached_until']);

            // Verify cached_until is a valid ISO 8601 timestamp
            $cachedUntil = $response->json('cached_until');
            $this->assertNotNull($cachedUntil);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $cachedUntil);
        }
    }

    /**
     * Mock the BigQueryService with a method and return value.
     *
     * @param  mixed  $returnValue
     */
    private function mockBigQueryService(string $method, $returnValue): Mockery\MockInterface
    {
        $mockService = Mockery::mock(BigQueryService::class);
        $mockService->shouldReceive($method)
            ->andReturn($returnValue);

        $this->app->instance(BigQueryService::class, $mockService);

        return $mockService;
    }
}
