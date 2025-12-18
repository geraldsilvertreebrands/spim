<?php

namespace Tests\Unit;

use App\Filament\Shared\Components\BrandSelector;
use App\Filament\Shared\Components\KpiTile;
use App\Filament\Shared\Components\PremiumLockedPlaceholder;
use App\Models\Brand;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_premium_locked_placeholder_renders(): void
    {
        $component = new PremiumLockedPlaceholder(
            feature: 'advanced analytics',
            contactEmail: 'test@example.com'
        );

        $this->assertEquals('advanced analytics', $component->feature);
        $this->assertEquals('test@example.com', $component->contactEmail);
        $this->assertEquals('Premium Feature', $component->title);
        $this->assertEquals('Upgrade to access', $component->description);
    }

    public function test_premium_locked_placeholder_has_defaults(): void
    {
        $component = new PremiumLockedPlaceholder;

        $this->assertEquals('this feature', $component->feature);
        $this->assertEquals('sales@silvertreebrands.com', $component->contactEmail);
    }

    public function test_kpi_tile_calculates_trend_up(): void
    {
        $component = new KpiTile(
            label: 'Revenue',
            value: 50000,
            change: 15.5
        );

        $this->assertEquals('up', $component->trendDirection);
        $this->assertStringContainsString('green', $component->trendColor);
        $this->assertEquals('+15.5%', $component->formattedChange());
    }

    public function test_kpi_tile_calculates_trend_down(): void
    {
        $component = new KpiTile(
            label: 'Revenue',
            value: 50000,
            change: -8.2
        );

        $this->assertEquals('down', $component->trendDirection);
        $this->assertStringContainsString('red', $component->trendColor);
        $this->assertEquals('-8.2%', $component->formattedChange());
    }

    public function test_kpi_tile_handles_neutral_trend(): void
    {
        $component = new KpiTile(
            label: 'Revenue',
            value: 50000,
            change: 0.0
        );

        $this->assertEquals('neutral', $component->trendDirection);
        $this->assertStringContainsString('gray', $component->trendColor);
    }

    public function test_kpi_tile_handles_null_change(): void
    {
        $component = new KpiTile(
            label: 'Revenue',
            value: 50000,
            change: null
        );

        $this->assertEquals('neutral', $component->trendDirection);
        $this->assertEquals('', $component->formattedChange());
    }

    public function test_kpi_tile_supports_prefix_and_suffix(): void
    {
        $component = new KpiTile(
            label: 'Revenue',
            value: 50000,
            prefix: 'R',
            suffix: 'ZAR'
        );

        $this->assertEquals('R', $component->prefix);
        $this->assertEquals('ZAR', $component->suffix);
    }

    public function test_brand_selector_returns_select_component(): void
    {
        $selector = BrandSelector::make();

        $this->assertInstanceOf(Select::class, $selector);
        $this->assertEquals('brand_id', $selector->getName());
    }

    public function test_brand_selector_standalone_returns_select_component(): void
    {
        $selector = BrandSelector::makeStandalone('custom_brand');

        $this->assertInstanceOf(Select::class, $selector);
        $this->assertEquals('custom_brand', $selector->getName());
    }

    public function test_admin_can_see_all_brands(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        Brand::factory()->create(['name' => 'Brand A']);
        Brand::factory()->create(['name' => 'Brand B']);

        $this->actingAs($admin);

        // Admin should see all brands via the accessible method
        $brandIds = $admin->accessibleBrandIds();
        $this->assertCount(2, $brandIds);
    }

    public function test_supplier_only_sees_assigned_brands(): void
    {
        $supplier = User::factory()->create(['is_active' => true]);
        $supplier->assignRole('supplier-basic');

        $brandA = Brand::factory()->create(['name' => 'Brand A']);
        Brand::factory()->create(['name' => 'Brand B']);

        // Assign only Brand A to the supplier
        $supplier->brands()->attach($brandA->id);

        $this->actingAs($supplier);

        $brandIds = $supplier->accessibleBrandIds();
        $this->assertCount(1, $brandIds);
        $this->assertEquals($brandA->id, $brandIds[0]);
    }
}
