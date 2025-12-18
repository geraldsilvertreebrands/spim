<?php

namespace Tests\Feature;

use App\Filament\PricingPanel\Pages\Dashboard;
use App\Models\PriceAlert;
use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $pricingUser;

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
    }

    public function test_dashboard_page_loads_for_authenticated_pricing_user(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 0,
            'avg_market_position' => 'unknown',
            'products_cheapest' => 0,
            'products_most_expensive' => 0,
            'recent_price_changes' => 0,
            'active_competitor_undercuts' => 0,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        // Test via Livewire component
        Livewire::test(Dashboard::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_dashboard_page_loads_for_admin_user(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 0,
            'avg_market_position' => 'unknown',
            'products_cheapest' => 0,
            'products_most_expensive' => 0,
            'recent_price_changes' => 0,
            'active_competitor_undercuts' => 0,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->adminUser);

        // Test via Livewire component
        Livewire::test(Dashboard::class)
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_dashboard_shows_kpis_with_bigquery(): void
    {
        // Mock BigQuery service
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

        $this->actingAs($this->pricingUser);

        Livewire::test(Dashboard::class)
            ->assertSet('loading', false)
            ->assertSet('error', null)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['products_tracked'] === 150)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['avg_market_position'] === 'competitive')
            ->assertSee('Products Tracked')
            ->assertSee('150')
            ->assertSee('Avg Price Position')
            ->assertSee('Competitive');
    }

    public function test_dashboard_handles_bigquery_error_gracefully(): void
    {
        // Mock BigQuery service that throws an exception
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->will($this->throwException(new \RuntimeException('BigQuery connection failed')));

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        Livewire::test(Dashboard::class)
            ->assertSet('error', 'Failed to load dashboard data. Please try again later.')
            ->assertSee('Error')
            ->assertSee('Failed to load dashboard data');
    }

    public function test_dashboard_shows_active_alerts_count(): void
    {
        // Create some price alerts
        PriceAlert::factory()->count(3)->create([
            'user_id' => $this->pricingUser->id,
            'is_active' => true,
        ]);
        PriceAlert::factory()->count(2)->create([
            'user_id' => $this->pricingUser->id,
            'is_active' => false,
        ]);

        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 50,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 20,
            'products_most_expensive' => 10,
            'recent_price_changes' => 15,
            'active_competitor_undercuts' => 5,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        Livewire::test(Dashboard::class)
            ->assertSet('loading', false)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['active_alerts'] === 3);
    }

    public function test_dashboard_shows_position_chart_data(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 100,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 30,
            'products_most_expensive' => 20,
            'recent_price_changes' => 25,
            'active_competitor_undercuts' => 8,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        $component = Livewire::test(Dashboard::class);

        $chartData = $component->get('positionChartData');

        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(4, $chartData['labels']);
        $this->assertEquals(['Cheapest', 'Below Avg', 'Above Avg', 'Most Expensive'], $chartData['labels']);
    }

    public function test_dashboard_shows_price_changes_chart_data(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 100,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 30,
            'products_most_expensive' => 20,
            'recent_price_changes' => 25,
            'active_competitor_undercuts' => 8,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        $component = Livewire::test(Dashboard::class);

        $chartData = $component->get('priceChangesChartData');

        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(7, $chartData['labels']); // 7 days of the week
        $this->assertCount(2, $chartData['datasets']); // Increases and Decreases
    }

    public function test_dashboard_shows_recent_alerts(): void
    {
        // Create some triggered alerts
        PriceAlert::factory()->count(3)->create([
            'user_id' => $this->pricingUser->id,
            'is_active' => true,
            'alert_type' => PriceAlert::TYPE_PRICE_CHANGE,
            'last_triggered_at' => now()->subHours(2),
        ]);

        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 50,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 20,
            'products_most_expensive' => 10,
            'recent_price_changes' => 15,
            'active_competitor_undercuts' => 5,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        $component = Livewire::test(Dashboard::class);

        $recentAlerts = $component->get('recentAlerts');

        $this->assertCount(3, $recentAlerts);
        $this->assertEquals('price_change', $recentAlerts[0]['type']);
    }

    public function test_refresh_action_reloads_data(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 50,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 20,
            'products_most_expensive' => 10,
            'recent_price_changes' => 15,
            'active_competitor_undercuts' => 5,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        Livewire::test(Dashboard::class)
            ->call('refresh')
            ->assertSet('loading', false)
            ->assertSet('error', null);
    }

    public function test_position_badge_class_for_competitive(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 100,
            'avg_market_position' => 'competitive',
            'products_cheapest' => 60,
            'products_most_expensive' => 5,
            'recent_price_changes' => 20,
            'active_competitor_undercuts' => 3,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        $component = Livewire::test(Dashboard::class);

        $badgeClass = $component->instance()->getPositionBadgeClass();
        $this->assertStringContainsString('green', $badgeClass);

        $label = $component->instance()->getPositionLabel();
        $this->assertEquals('Competitive', $label);
    }

    public function test_position_badge_class_for_premium(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 100,
            'avg_market_position' => 'premium',
            'products_cheapest' => 10,
            'products_most_expensive' => 55,
            'recent_price_changes' => 20,
            'active_competitor_undercuts' => 15,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        $component = Livewire::test(Dashboard::class);

        $badgeClass = $component->instance()->getPositionBadgeClass();
        $this->assertStringContainsString('red', $badgeClass);

        $label = $component->instance()->getPositionLabel();
        $this->assertEquals('Premium', $label);
    }

    public function test_position_badge_class_for_mid_market(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 100,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 30,
            'products_most_expensive' => 30,
            'recent_price_changes' => 20,
            'active_competitor_undercuts' => 10,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        $component = Livewire::test(Dashboard::class);

        $badgeClass = $component->instance()->getPositionBadgeClass();
        $this->assertStringContainsString('yellow', $badgeClass);

        $label = $component->instance()->getPositionLabel();
        $this->assertEquals('Mid-Market', $label);
    }

    public function test_local_data_mode_loads_without_error(): void
    {
        // Mock BigQuery for initial mount
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 0,
            'avg_market_position' => 'unknown',
            'products_cheapest' => 0,
            'products_most_expensive' => 0,
            'recent_price_changes' => 0,
            'active_competitor_undercuts' => 0,
        ]);
        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        // Test with local data (useBigQuery = false)
        // This tests that the local data mode doesn't crash when there's no data
        $component = Livewire::test(Dashboard::class);
        $component->set('useBigQuery', false);
        $component->call('loadData');

        $kpis = $component->get('kpis');
        $this->assertArrayHasKey('products_tracked', $kpis);
        $this->assertArrayHasKey('active_alerts', $kpis);
        $this->assertArrayHasKey('avg_market_position', $kpis);
        $this->assertEquals(0, $kpis['active_alerts']); // No alerts in test
    }

    public function test_toggle_data_source_switches_mode(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 50,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 20,
            'products_most_expensive' => 10,
            'recent_price_changes' => 15,
            'active_competitor_undercuts' => 5,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        Livewire::test(Dashboard::class)
            ->assertSet('useBigQuery', true)
            ->call('toggleDataSource')
            ->assertSet('useBigQuery', false)
            ->call('toggleDataSource')
            ->assertSet('useBigQuery', true);
    }

    public function test_unauthorized_user_cannot_access_pricing_panel(): void
    {
        // Create a user without pricing access
        $regularUser = User::factory()->create();
        $regularUser->assignRole('pim-editor');

        $this->actingAs($regularUser);

        $response = $this->get('/pricing');

        $response->assertStatus(403);
    }

    public function test_dashboard_displays_secondary_kpis(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 100,
            'avg_market_position' => 'mid-market',
            'products_cheapest' => 35,
            'products_most_expensive' => 25,
            'recent_price_changes' => 40,
            'active_competitor_undercuts' => 18,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        $this->actingAs($this->pricingUser);

        Livewire::test(Dashboard::class)
            ->assertSee('Cheapest in Market')
            ->assertSee('35')
            ->assertSee('Most Expensive')
            ->assertSee('25')
            ->assertSee('Competitor Undercuts')
            ->assertSee('18');
    }

    public function test_dashboard_kpi_tiles_display_correctly(): void
    {
        // Mock BigQuery service
        $mockBQ = $this->createMock(BigQueryService::class);
        $mockBQ->method('isConfigured')->willReturn(true);
        $mockBQ->method('getPricingKpis')->willReturn([
            'products_tracked' => 200,
            'avg_market_position' => 'competitive',
            'products_cheapest' => 100,
            'products_most_expensive' => 20,
            'recent_price_changes' => 50,
            'active_competitor_undercuts' => 10,
        ]);

        $this->app->instance(BigQueryService::class, $mockBQ);

        // Create some active alerts
        PriceAlert::factory()->count(5)->create([
            'user_id' => $this->pricingUser->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->pricingUser);

        Livewire::test(Dashboard::class)
            ->assertSee('Products Tracked')
            ->assertSee('200')
            ->assertSee('Price Changes')
            ->assertSee('50')
            ->assertSee('Active Alerts')
            ->assertSee('5')
            ->assertSee('This week');
    }
}
