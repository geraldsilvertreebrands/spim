<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Entity $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product', 'description' => 'Test product entity type']
        );
        $this->product = Entity::factory()->create(['entity_type_id' => $entityType->id]);
    }

    // =====================
    // Basic CRUD Tests
    // =====================

    public function test_can_create_price_alert(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->priceBelow(100.00)
            ->create();

        $this->assertDatabaseHas('price_alerts', [
            'id' => $alert->id,
            'user_id' => $this->user->id,
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
            'is_active' => true,
        ]);
    }

    public function test_can_create_alert_for_specific_product(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(50.00)
            ->create();

        $this->assertEquals($this->product->id, $alert->product_id);
    }

    public function test_can_create_alert_for_specific_competitor(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forCompetitor('Takealot')
            ->competitorBeats()
            ->create();

        $this->assertEquals('Takealot', $alert->competitor_name);
    }

    public function test_alert_type_constants_are_valid(): void
    {
        $this->assertEquals('price_below', PriceAlert::TYPE_PRICE_BELOW);
        $this->assertEquals('competitor_beats', PriceAlert::TYPE_COMPETITOR_BEATS);
        $this->assertEquals('price_change', PriceAlert::TYPE_PRICE_CHANGE);
        $this->assertEquals('out_of_stock', PriceAlert::TYPE_OUT_OF_STOCK);

        $this->assertCount(4, PriceAlert::ALERT_TYPES);
    }

    // =====================
    // Relationship Tests
    // =====================

    public function test_belongs_to_user(): void
    {
        $alert = PriceAlert::factory()->forUser($this->user)->create();

        $this->assertInstanceOf(User::class, $alert->user);
        $this->assertEquals($this->user->id, $alert->user->id);
    }

    public function test_belongs_to_product(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->create();

        $this->assertInstanceOf(Entity::class, $alert->product);
        $this->assertEquals($this->product->id, $alert->product->id);
    }

    public function test_product_can_be_null(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->global()
            ->create();

        $this->assertNull($alert->product_id);
        $this->assertNull($alert->product);
    }

    public function test_user_can_have_many_alerts(): void
    {
        PriceAlert::factory()->forUser($this->user)->count(5)->create();

        $this->assertCount(5, $this->user->priceAlerts);
    }

    public function test_cascade_delete_when_user_deleted(): void
    {
        $alert = PriceAlert::factory()->forUser($this->user)->create();
        $alertId = $alert->id;

        $this->user->delete();

        $this->assertDatabaseMissing('price_alerts', ['id' => $alertId]);
    }

    // =====================
    // Scope Tests
    // =====================

    public function test_scope_active_filters_correctly(): void
    {
        PriceAlert::factory()->forUser($this->user)->active()->count(3)->create();
        PriceAlert::factory()->forUser($this->user)->inactive()->count(2)->create();

        $this->assertCount(3, PriceAlert::active()->get());
    }

    public function test_scope_inactive_filters_correctly(): void
    {
        PriceAlert::factory()->forUser($this->user)->active()->count(3)->create();
        PriceAlert::factory()->forUser($this->user)->inactive()->count(2)->create();

        $this->assertCount(2, PriceAlert::inactive()->get());
    }

    public function test_scope_for_user_filters_correctly(): void
    {
        $otherUser = User::factory()->create();

        PriceAlert::factory()->forUser($this->user)->count(3)->create();
        PriceAlert::factory()->forUser($otherUser)->count(2)->create();

        $this->assertCount(3, PriceAlert::forUser($this->user)->get());
        $this->assertCount(2, PriceAlert::forUser($otherUser)->get());
    }

    public function test_scope_for_product_filters_correctly(): void
    {
        $entityType = EntityType::firstOrCreate(
            ['name' => 'other_product'],
            ['display_name' => 'Other', 'description' => 'Another type']
        );
        $otherProduct = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        PriceAlert::factory()->forUser($this->user)->forProduct($this->product)->count(3)->create();
        PriceAlert::factory()->forUser($this->user)->forProduct($otherProduct)->count(2)->create();

        $this->assertCount(3, PriceAlert::forProduct($this->product)->get());
    }

    public function test_scope_by_type_filters_correctly(): void
    {
        PriceAlert::factory()->forUser($this->user)->priceBelow(100)->count(3)->create();
        PriceAlert::factory()->forUser($this->user)->outOfStock()->count(2)->create();

        $this->assertCount(3, PriceAlert::byType(PriceAlert::TYPE_PRICE_BELOW)->get());
        $this->assertCount(2, PriceAlert::byType(PriceAlert::TYPE_OUT_OF_STOCK)->get());
    }

    public function test_scope_not_triggered_since_filters_correctly(): void
    {
        PriceAlert::factory()->forUser($this->user)->neverTriggered()->count(2)->create();
        PriceAlert::factory()->forUser($this->user)->triggeredRecently()->count(1)->create();
        PriceAlert::factory()->forUser($this->user)->triggeredLongAgo()->count(1)->create();

        // Not triggered in the last hour
        $results = PriceAlert::notTriggeredSince(now()->subHour())->get();

        // Should include: never triggered (2) + triggered long ago (1) = 3
        $this->assertCount(3, $results);
    }

    // =====================
    // Alert Matching Tests
    // =====================

    public function test_matches_scrape_with_specific_product(): void
    {
        // Create a fresh product specifically for this test
        $entityType = EntityType::firstOrCreate(
            ['name' => 'match_test_product'],
            ['display_name' => 'Match Test', 'description' => 'Test type']
        );
        $testProduct = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        // Create alert for specific product but any competitor (global for competitor)
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->priceBelow(100)
            ->create([
                'product_id' => $testProduct->id,
                'competitor_name' => null, // Global for competitor
            ]);

        // Create scrape with explicit product_id
        $matchingScrape = PriceScrape::factory()->create([
            'product_id' => $testProduct->id,
        ]);

        $otherEntityType = EntityType::firstOrCreate(
            ['name' => 'other_match_product'],
            ['display_name' => 'Other', 'description' => 'Another type']
        );
        $otherProduct = Entity::factory()->create(['entity_type_id' => $otherEntityType->id]);

        $nonMatchingScrape = PriceScrape::factory()->create([
            'product_id' => $otherProduct->id,
        ]);

        $this->assertTrue($alert->matchesScrape($matchingScrape));
        $this->assertFalse($alert->matchesScrape($nonMatchingScrape));
    }

    public function test_matches_scrape_with_specific_competitor(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forCompetitor('Takealot')
            ->priceBelow(100)
            ->create();

        $matchingScrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->create();

        $nonMatchingScrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->create();

        $this->assertTrue($alert->matchesScrape($matchingScrape));
        $this->assertFalse($alert->matchesScrape($nonMatchingScrape));
    }

    public function test_global_alert_matches_any_scrape(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->global()
            ->priceBelow(100)
            ->create();

        $scrape1 = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->create();

        $scrape2 = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->create();

        $this->assertTrue($alert->matchesScrape($scrape1));
        $this->assertTrue($alert->matchesScrape($scrape2));
    }

    // =====================
    // Price Below Alert Tests
    // =====================

    public function test_price_below_triggers_when_price_under_threshold(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        $this->assertTrue($alert->shouldTrigger($scrape));
    }

    public function test_price_below_does_not_trigger_when_price_above_threshold(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(120.00)
            ->scrapedToday()
            ->create();

        $this->assertFalse($alert->shouldTrigger($scrape));
    }

    public function test_price_below_does_not_trigger_at_exact_threshold(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(100.00)
            ->scrapedToday()
            ->create();

        $this->assertFalse($alert->shouldTrigger($scrape));
    }

    // =====================
    // Competitor Beats Alert Tests
    // =====================

    public function test_competitor_beats_triggers_when_competitor_cheaper(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->competitorBeats()
            ->create([
                'product_id' => $this->product->id,
                'competitor_name' => null, // Global for competitor
            ]);

        $scrape = PriceScrape::factory()
            ->withPrice(80.00)
            ->scrapedToday()
            ->create(['product_id' => $this->product->id]);

        // Our price is 100, competitor is 80
        $this->assertTrue($alert->shouldTrigger($scrape, 100.00));
    }

    public function test_competitor_beats_does_not_trigger_when_we_are_cheaper(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->competitorBeats()
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(120.00)
            ->scrapedToday()
            ->create();

        // Our price is 100, competitor is 120
        $this->assertFalse($alert->shouldTrigger($scrape, 100.00));
    }

    public function test_competitor_beats_does_not_trigger_without_our_price(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->competitorBeats()
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        // No our price provided
        $this->assertFalse($alert->shouldTrigger($scrape, null));
    }

    // =====================
    // Price Change Alert Tests
    // =====================

    public function test_price_change_triggers_when_change_exceeds_threshold(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->priceChange(10.00) // 10% threshold
            ->create();

        // Create previous scrape
        PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        // Create current scrape with 15% increase
        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(115.00)
            ->scrapedToday()
            ->create();

        $this->assertTrue($alert->shouldTrigger($scrape));
    }

    public function test_price_change_does_not_trigger_when_change_below_threshold(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->priceChange(10.00) // 10% threshold
            ->create();

        // Create previous scrape
        PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        // Create current scrape with 5% increase
        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(105.00)
            ->scrapedToday()
            ->create();

        $this->assertFalse($alert->shouldTrigger($scrape));
    }

    public function test_price_change_triggers_for_decrease(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->priceChange(10.00)
            ->create();

        // Create previous scrape
        PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        // Create current scrape with 15% decrease
        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(85.00)
            ->scrapedToday()
            ->create();

        $this->assertTrue($alert->shouldTrigger($scrape));
    }

    // =====================
    // Out of Stock Alert Tests
    // =====================

    public function test_out_of_stock_triggers_when_status_changes_to_out_of_stock(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->outOfStock()
            ->create();

        // Create previous scrape - in stock
        PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->inStock()
            ->scrapedDaysAgo(1)
            ->create();

        // Create current scrape - out of stock
        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->outOfStock()
            ->scrapedToday()
            ->create();

        $this->assertTrue($alert->shouldTrigger($scrape));
    }

    public function test_out_of_stock_does_not_trigger_if_already_out_of_stock(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->outOfStock()
            ->create();

        // Create previous scrape - already out of stock
        PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->outOfStock()
            ->scrapedDaysAgo(1)
            ->create();

        // Create current scrape - still out of stock
        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->outOfStock()
            ->scrapedToday()
            ->create();

        $this->assertFalse($alert->shouldTrigger($scrape));
    }

    public function test_out_of_stock_does_not_trigger_when_in_stock(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->outOfStock()
            ->create();

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->inStock()
            ->scrapedToday()
            ->create();

        $this->assertFalse($alert->shouldTrigger($scrape));
    }

    // =====================
    // Inactive Alert Tests
    // =====================

    public function test_inactive_alert_never_triggers(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forProduct($this->product)
            ->priceBelow(100.00)
            ->inactive()
            ->create(['competitor_name' => null]);

        $scrape = PriceScrape::factory()
            ->forProduct($this->product)
            ->withPrice(50.00) // Well below threshold
            ->scrapedToday()
            ->create();

        $this->assertFalse($alert->shouldTrigger($scrape));
    }

    // =====================
    // Alert Actions Tests
    // =====================

    public function test_mark_triggered_updates_timestamp(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->neverTriggered()
            ->create();

        $this->assertNull($alert->last_triggered_at);

        $alert->markTriggered();

        $alert->refresh();
        $this->assertNotNull($alert->last_triggered_at);
        $this->assertTrue($alert->last_triggered_at->isToday());
    }

    public function test_activate_sets_is_active_to_true(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->inactive()
            ->create();

        $this->assertFalse($alert->is_active);

        $alert->activate();

        $alert->refresh();
        $this->assertTrue($alert->is_active);
    }

    public function test_deactivate_sets_is_active_to_false(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->active()
            ->create();

        $this->assertTrue($alert->is_active);

        $alert->deactivate();

        $alert->refresh();
        $this->assertFalse($alert->is_active);
    }

    public function test_was_triggered_recently_returns_correct_value(): void
    {
        $recentlyTriggered = PriceAlert::factory()
            ->forUser($this->user)
            ->triggeredAt(now()->subMinutes(30))
            ->create();

        $longAgoTriggered = PriceAlert::factory()
            ->forUser($this->user)
            ->triggeredAt(now()->subMinutes(90))
            ->create();

        $neverTriggered = PriceAlert::factory()
            ->forUser($this->user)
            ->neverTriggered()
            ->create();

        $this->assertTrue($recentlyTriggered->wasTriggeredRecently(60));
        $this->assertFalse($longAgoTriggered->wasTriggeredRecently(60));
        $this->assertFalse($neverTriggered->wasTriggeredRecently(60));
    }

    // =====================
    // Static Methods Tests
    // =====================

    public function test_get_triggered_alerts_returns_matching_alerts(): void
    {
        // Create an alert that should trigger
        $matchingAlert = PriceAlert::factory()
            ->forUser($this->user)
            ->priceBelow(100.00)
            ->active()
            ->neverTriggered()
            ->create([
                'product_id' => $this->product->id,
                'competitor_name' => null, // Global for competitor
            ]);

        // Create an alert that shouldn't trigger (threshold too low)
        PriceAlert::factory()
            ->forUser($this->user)
            ->priceBelow(50.00)
            ->active()
            ->neverTriggered()
            ->create([
                'product_id' => $this->product->id,
                'competitor_name' => null, // Global for competitor
            ]);

        $scrape = PriceScrape::factory()
            ->withPrice(80.00)
            ->scrapedToday()
            ->create(['product_id' => $this->product->id]);

        $triggered = PriceAlert::getTriggeredAlerts($scrape);

        $this->assertCount(1, $triggered);
        $this->assertEquals($matchingAlert->id, $triggered->first()->id);
    }

    public function test_get_triggered_alerts_respects_cooldown(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->priceBelow(100.00)
            ->active()
            ->triggeredAt(now()->subMinutes(30)) // Triggered 30 mins ago
            ->create([
                'product_id' => $this->product->id,
                'competitor_name' => null, // Global for competitor
            ]);

        $scrape = PriceScrape::factory()
            ->withPrice(80.00)
            ->scrapedToday()
            ->create(['product_id' => $this->product->id]);

        // With 60 minute cooldown, should not trigger
        $triggered = PriceAlert::getTriggeredAlerts($scrape, null, 60);
        $this->assertCount(0, $triggered);

        // With 15 minute cooldown, should trigger
        $triggered = PriceAlert::getTriggeredAlerts($scrape, null, 15);
        $this->assertCount(1, $triggered);
    }

    public function test_get_alert_type_labels_returns_all_types(): void
    {
        $labels = PriceAlert::getAlertTypeLabels();

        $this->assertArrayHasKey(PriceAlert::TYPE_PRICE_BELOW, $labels);
        $this->assertArrayHasKey(PriceAlert::TYPE_COMPETITOR_BEATS, $labels);
        $this->assertArrayHasKey(PriceAlert::TYPE_PRICE_CHANGE, $labels);
        $this->assertArrayHasKey(PriceAlert::TYPE_OUT_OF_STOCK, $labels);
    }

    public function test_get_description_returns_human_readable_string(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->forCompetitor('Takealot')
            ->priceBelow(100.00)
            ->create();

        $description = $alert->getDescription();

        $this->assertStringContainsString('100', $description);
        $this->assertStringContainsString('Takealot', $description);
    }

    // =====================
    // Factory Tests
    // =====================

    public function test_factory_creates_valid_data(): void
    {
        $alert = PriceAlert::factory()->forUser($this->user)->create();

        $this->assertNotNull($alert->user_id);
        $this->assertNotNull($alert->alert_type);
        $this->assertContains($alert->alert_type, PriceAlert::ALERT_TYPES);
    }

    public function test_factory_price_below_state(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->priceBelow(150.00)
            ->create();

        $this->assertEquals(PriceAlert::TYPE_PRICE_BELOW, $alert->alert_type);
        $this->assertEquals(150.00, $alert->threshold);
    }

    public function test_factory_competitor_beats_state(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->competitorBeats()
            ->create();

        $this->assertEquals(PriceAlert::TYPE_COMPETITOR_BEATS, $alert->alert_type);
        $this->assertNull($alert->threshold);
    }

    public function test_factory_price_change_state(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->priceChange(15.0)
            ->create();

        $this->assertEquals(PriceAlert::TYPE_PRICE_CHANGE, $alert->alert_type);
        $this->assertEquals(15.00, $alert->threshold);
    }

    public function test_factory_out_of_stock_state(): void
    {
        $alert = PriceAlert::factory()
            ->forUser($this->user)
            ->outOfStock()
            ->create();

        $this->assertEquals(PriceAlert::TYPE_OUT_OF_STOCK, $alert->alert_type);
        $this->assertNull($alert->threshold);
    }
}
