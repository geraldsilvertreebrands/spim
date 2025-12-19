<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplyDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $supplierUser;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Create admin user
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Create supplier user
        $this->supplierUser = User::factory()->create();
        $this->supplierUser->assignRole('supplier-basic');

        // Create a brand
        $this->brand = Brand::factory()->create([
            'name' => 'Test Brand',
        ]);

        // Associate supplier with brand
        $this->supplierUser->brands()->attach($this->brand->id);
    }

    public function test_dashboard_page_loads_for_authenticated_supplier(): void
    {
        $this->actingAs($this->supplierUser);

        // Use Livewire test to bypass Filament's auth middleware
        // The HTTP route requires session-based auth through Filament's login
        Livewire::test(\App\Filament\SupplyPanel\Pages\Dashboard::class, ['brandId' => $this->brand->id])
            ->assertStatus(200);
    }

    public function test_dashboard_shows_kpis_when_bigquery_configured(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->willReturn([
            'revenue' => 125000,
            'orders' => 450,
            'units' => 1200,
            'aov' => 277,
            'revenue_change' => 12.0,
            'orders_change' => 8.0,
            'units_change' => 15.0,
            'aov_change' => -3.0,
        ]);
        $mockBQ->method('getSalesTrend')->willReturn([
            'labels' => ['2025-01', '2025-02'],
            'datasets' => [
                [
                    'label' => 'Test Brand',
                    'data' => [100000, 125000],
                    'borderColor' => '#006654',
                    'backgroundColor' => 'rgba(0, 102, 84, 0.1)',
                ],
            ],
        ]);
        $mockBQ->method('getTopProducts')->willReturn([
            [
                'name' => 'Product 1',
                'revenue' => 45000,
                'units' => 500,
                'growth' => 20.0,
            ],
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->supplierUser);

        Livewire::test(\App\Filament\SupplyPanel\Pages\Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('brandId', $this->brand->id)
            ->assertSet('loading', false)
            ->assertSet('error', null)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['revenue'] === 125000)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['orders'] === 450)
            ->assertSee('Net Revenue')
            ->assertSee('R125,000')
            ->assertSee('Total Orders')
            ->assertSee('450');
    }

    public function test_dashboard_handles_bigquery_error_gracefully(): void
    {
        // Mock BigQuery service that throws an exception
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->will($this->throwException(new \RuntimeException('BigQuery connection failed')));

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->supplierUser);

        Livewire::test(\App\Filament\SupplyPanel\Pages\Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('error', 'Failed to load dashboard data: BigQuery connection failed')
            ->assertSee('Error')
            ->assertSee('Failed to load dashboard data');
    }

    public function test_supplier_can_only_see_assigned_brands(): void
    {
        // Create another brand that the supplier doesn't have access to
        $otherBrand = Brand::factory()->create(['name' => 'Other Brand']);

        $this->actingAs($this->supplierUser);

        Livewire::test(\App\Filament\SupplyPanel\Pages\Dashboard::class, ['brandId' => $otherBrand->id])
            ->assertSet('error', 'You do not have access to this brand.')
            ->assertSee('Error');
    }

    public function test_admin_can_see_all_brands(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->willReturn([
            'revenue' => 100000,
            'orders' => 400,
            'units' => 1000,
            'aov' => 250,
            'revenue_change' => null,
            'orders_change' => null,
            'units_change' => null,
            'aov_change' => null,
        ]);
        $mockBQ->method('getSalesTrend')->willReturn([
            'labels' => [],
            'datasets' => [],
        ]);
        $mockBQ->method('getTopProducts')->willReturn([]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->adminUser);

        Livewire::test(\App\Filament\SupplyPanel\Pages\Dashboard::class, ['brandId' => $this->brand->id])
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    public function test_period_filter_updates_data(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getBrandKpis')->willReturn([
            'revenue' => 100000,
            'orders' => 400,
            'units' => 1000,
            'aov' => 250,
            'revenue_change' => null,
            'orders_change' => null,
            'units_change' => null,
            'aov_change' => null,
        ]);
        $mockBQ->method('getSalesTrend')->willReturn([
            'labels' => [],
            'datasets' => [],
        ]);
        $mockBQ->method('getTopProducts')->willReturn([]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->supplierUser);

        Livewire::test(\App\Filament\SupplyPanel\Pages\Dashboard::class, ['brandId' => $this->brand->id])
            ->set('period', '90d')
            ->assertSet('period', '90d')
            ->assertSet('loading', false);
    }

    public function test_brand_selector_shows_multiple_brands(): void
    {
        // Create another brand for the supplier
        $brand2 = Brand::factory()->create(['name' => 'Second Brand']);
        $this->supplierUser->brands()->attach($brand2->id);

        $this->actingAs($this->supplierUser);

        $component = Livewire::test(\App\Filament\SupplyPanel\Pages\Dashboard::class, ['brandId' => $this->brand->id]);

        // getAvailableBrands() is a method that returns array of brands
        $availableBrands = $component->instance()->getAvailableBrands();
        $this->assertCount(2, $availableBrands);
        $this->assertArrayHasKey($this->brand->id, $availableBrands);
        $this->assertArrayHasKey($brand2->id, $availableBrands);
    }
}
