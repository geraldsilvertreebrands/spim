<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use App\Services\PriceAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceAlertService $service;

    private User $user;

    /** @var Entity */
    private $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PriceAlertService;
        $this->user = User::factory()->create();

        $entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product', 'description' => 'Test product entity type']
        );
        $this->product = Entity::factory()->create(['entity_type_id' => $entityType->id]);
    }

    // =====================
    // Process Scrape Tests
    // =====================

    public function test_process_scrape_returns_triggered_alerts(): void
    {
        // Create an alert that should trigger
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        $triggered = $this->service->processScrape($scrape);

        $this->assertCount(1, $triggered);
        $this->assertEquals($alert->id, $triggered->first()->id);
    }

    public function test_process_scrape_marks_alerts_as_triggered(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        $this->assertNull($alert->last_triggered_at);

        $this->service->processScrape($scrape);

        $alert->refresh();
        $this->assertNotNull($alert->last_triggered_at);
    }

    public function test_process_scrape_passes_our_price_for_competitor_beats(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->competitorBeats()
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        // Without our price, should not trigger
        $triggered = $this->service->processScrape($scrape, null);
        $this->assertCount(0, $triggered);

        // Reset alert
        $alert->update(['last_triggered_at' => null]);

        // With our price higher than competitor, should trigger
        $triggered = $this->service->processScrape($scrape, 100.00);
        $this->assertCount(1, $triggered);
    }

    public function test_process_scrape_respects_cooldown(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->active()
            ->triggeredAt(now()->subMinutes(30))
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        // With default 60 minute cooldown, should not trigger
        $triggered = $this->service->processScrape($scrape);
        $this->assertCount(0, $triggered);

        // With custom 15 minute cooldown, should trigger
        $triggered = $this->service->processScrape($scrape, null, 15);
        $this->assertCount(1, $triggered);
    }

    // =====================
    // Process Multiple Scrapes Tests
    // =====================

    public function test_process_multiple_scrapes_returns_all_triggered(): void
    {
        // Create alerts for different products
        $entityType = EntityType::firstOrCreate(
            ['name' => 'product2'],
            ['display_name' => 'Product 2', 'description' => 'Another type']
        );
        $product2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($product2)
            ->priceBelow(200.00)
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        $scrape1 = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        $scrape2 = PriceScrape::factory()
            ->forProduct($product2)
            ->withPrice(150.00)
            ->scrapedToday()
            ->create();

        $results = $this->service->processMultipleScrapes(collect([$scrape1, $scrape2]));

        $this->assertCount(2, $results);
    }

    public function test_process_multiple_scrapes_uses_our_prices_map(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->competitorBeats()
            ->active()
            ->neverTriggered()
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        $ourPrices = [
            $this->product->id => 100.00,
        ];

        $results = $this->service->processMultipleScrapes(collect([$scrape]), $ourPrices);

        $this->assertCount(1, $results);
    }

    // =====================
    // Create Alert Tests
    // =====================

    public function test_create_alert_creates_price_alert(): void
    {
        $alert = $this->service->createAlert($this->user, [
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
            'product_id' => $this->product->id,
            'competitor_name' => 'Takealot',
        ]);

        $this->assertDatabaseHas('price_alerts', [
            'id' => $alert->id,
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'competitor_name' => 'Takealot',
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
            'is_active' => true,
        ]);
    }

    public function test_create_price_below_alert(): void
    {
        $alert = $this->service->createPriceBelowAlert(
            $this->user,
            150.00,
            $this->product->id,
            'Takealot'
        );

        $this->assertEquals(PriceAlert::TYPE_PRICE_BELOW, $alert->alert_type);
        $this->assertEquals(150.00, $alert->threshold);
        $this->assertEquals($this->product->id, $alert->product_id);
        $this->assertEquals('Takealot', $alert->competitor_name);
    }

    public function test_create_competitor_beats_alert(): void
    {
        $alert = $this->service->createCompetitorBeatsAlert(
            $this->user,
            $this->product->id,
            'Clicks'
        );

        $this->assertEquals(PriceAlert::TYPE_COMPETITOR_BEATS, $alert->alert_type);
        $this->assertNull($alert->threshold);
    }

    public function test_create_price_change_alert(): void
    {
        $alert = $this->service->createPriceChangeAlert($this->user, 10.0);

        $this->assertEquals(PriceAlert::TYPE_PRICE_CHANGE, $alert->alert_type);
        $this->assertEquals(10.00, $alert->threshold);
    }

    public function test_create_out_of_stock_alert(): void
    {
        $alert = $this->service->createOutOfStockAlert($this->user);

        $this->assertEquals(PriceAlert::TYPE_OUT_OF_STOCK, $alert->alert_type);
    }

    // =====================
    // Get Alerts Tests
    // =====================

    public function test_get_user_alerts_returns_all_user_alerts(): void
    {
        PriceAlert::factory()->forUser($this->user)->active()->count(3)->create();
        PriceAlert::factory()->forUser($this->user)->inactive()->count(2)->create();

        $alerts = $this->service->getUserAlerts($this->user);

        $this->assertCount(5, $alerts);
    }

    public function test_get_user_alerts_filters_active_only(): void
    {
        PriceAlert::factory()->forUser($this->user)->active()->count(3)->create();
        PriceAlert::factory()->forUser($this->user)->inactive()->count(2)->create();

        $alerts = $this->service->getUserAlerts($this->user, activeOnly: true);

        $this->assertCount(3, $alerts);
    }

    public function test_get_product_alerts_returns_product_alerts(): void
    {
        PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->count(3)
            ->create();

        $alerts = $this->service->getProductAlerts($this->product);

        $this->assertCount(3, $alerts);
    }

    // =====================
    // Alert Stats Tests
    // =====================

    public function test_get_alert_stats_returns_correct_counts(): void
    {
        PriceAlert::factory()
            ->forUser($this->user)
            ->priceBelow(100)
            ->active()
            ->count(3)
            ->create();

        PriceAlert::factory()
            ->forUser($this->user)
            ->outOfStock()
            ->inactive()
            ->count(2)
            ->create();

        PriceAlert::factory()
            ->forUser($this->user)
            ->priceChange(10)
            ->active()
            ->triggeredAt(now()->subHour())
            ->create();

        $stats = $this->service->getAlertStats($this->user);

        $this->assertEquals(6, $stats['total']);
        $this->assertEquals(4, $stats['active']);
        $this->assertEquals(2, $stats['inactive']);
        $this->assertArrayHasKey('by_type', $stats);
    }

    // =====================
    // Bulk Operations Tests
    // =====================

    public function test_activate_alerts_activates_specified_alerts(): void
    {
        $alerts = PriceAlert::factory()
            ->forUser($this->user)
            ->inactive()
            ->count(3)
            ->create();

        $alertIds = $alerts->pluck('id')->toArray();

        $count = $this->service->activateAlerts($alertIds);

        $this->assertEquals(3, $count);
        $this->assertEquals(3, PriceAlert::whereIn('id', $alertIds)->where('is_active', true)->count());
    }

    public function test_deactivate_alerts_deactivates_specified_alerts(): void
    {
        $alerts = PriceAlert::factory()
            ->forUser($this->user)
            ->active()
            ->count(3)
            ->create();

        $alertIds = $alerts->pluck('id')->toArray();

        $count = $this->service->deactivateAlerts($alertIds);

        $this->assertEquals(3, $count);
        $this->assertEquals(3, PriceAlert::whereIn('id', $alertIds)->where('is_active', false)->count());
    }

    public function test_delete_alerts_deletes_user_alerts(): void
    {
        $alerts = PriceAlert::factory()
            ->forUser($this->user)
            ->count(3)
            ->create();

        $alertIds = $alerts->take(2)->pluck('id')->toArray();

        $count = $this->service->deleteAlerts($this->user, $alertIds);

        $this->assertEquals(2, $count);
        $this->assertEquals(1, PriceAlert::forUser($this->user)->count());
    }

    public function test_delete_alerts_deletes_all_when_no_ids_provided(): void
    {
        PriceAlert::factory()->forUser($this->user)->count(5)->create();

        $count = $this->service->deleteAlerts($this->user);

        $this->assertEquals(5, $count);
        $this->assertEquals(0, PriceAlert::forUser($this->user)->count());
    }

    // =====================
    // Cooldown Configuration Tests
    // =====================

    public function test_set_cooldown_minutes_configures_service(): void
    {
        $this->service->setCooldownMinutes(30);

        $this->assertEquals(30, $this->service->getCooldownMinutes());
    }

    public function test_default_cooldown_is_60_minutes(): void
    {
        $service = new PriceAlertService;

        $this->assertEquals(60, $service->getCooldownMinutes());
    }
}
