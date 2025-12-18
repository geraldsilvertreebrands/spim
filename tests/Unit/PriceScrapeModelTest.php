<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceScrape;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceScrapeModelTest extends TestCase
{
    use RefreshDatabase;

    private Entity $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create or find a product entity type for testing
        $entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product', 'description' => 'Test product entity type']
        );
        $this->product = Entity::factory()->create(['entity_type_id' => $entityType->id]);
    }

    // =====================
    // Basic CRUD Tests
    // =====================

    public function test_can_create_price_scrape(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create([
            'competitor_name' => 'Takealot',
            'price' => 99.99,
            'currency' => 'ZAR',
            'in_stock' => true,
        ]);

        $this->assertDatabaseHas('price_scrapes', [
            'id' => $scrape->id,
            'product_id' => $this->product->id,
            'competitor_name' => 'Takealot',
            'price' => 99.99,
            'currency' => 'ZAR',
            'in_stock' => true,
        ]);
    }

    public function test_can_create_price_scrape_with_nullable_fields(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create([
            'competitor_url' => null,
            'competitor_sku' => null,
        ]);

        $this->assertDatabaseHas('price_scrapes', [
            'id' => $scrape->id,
            'competitor_url' => null,
            'competitor_sku' => null,
        ]);
    }

    public function test_price_is_cast_to_decimal(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create([
            'price' => 99.999,
        ]);

        // Database stores it as decimal(10,2), so it rounds
        $this->assertEquals('100.00', $scrape->fresh()->price);
    }

    public function test_in_stock_is_cast_to_boolean(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create([
            'in_stock' => 1,
        ]);

        $this->assertIsBool($scrape->in_stock);
        $this->assertTrue($scrape->in_stock);
    }

    public function test_scraped_at_is_cast_to_datetime(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create();

        $this->assertInstanceOf(Carbon::class, $scrape->scraped_at);
    }

    // =====================
    // Relationship Tests
    // =====================

    public function test_belongs_to_product(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create();

        $this->assertInstanceOf(Entity::class, $scrape->product);
        $this->assertEquals($this->product->id, $scrape->product->id);
    }

    public function test_cascade_delete_when_product_deleted(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create();
        $scrapeId = $scrape->id;

        $this->product->delete();

        $this->assertDatabaseMissing('price_scrapes', ['id' => $scrapeId]);
    }

    // =====================
    // Date Range Scope Tests
    // =====================

    public function test_scope_date_range_filters_correctly(): void
    {
        $oldScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedAt(Carbon::parse('2024-01-01'))->create();
        $midScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedAt(Carbon::parse('2024-06-15'))->create();
        $newScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedAt(Carbon::parse('2024-12-01'))->create();

        $results = PriceScrape::dateRange('2024-06-01', '2024-07-01')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($midScrape->id, $results->first()->id);
    }

    public function test_scope_date_range_accepts_carbon_instances(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedAt(now()->subDays(5))->create();

        $results = PriceScrape::dateRange(now()->subDays(10), now())->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_last_days_filters_correctly(): void
    {
        $oldScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(15)->create();
        $recentScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(5)->create();

        $results = PriceScrape::lastDays(7)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($recentScrape->id, $results->first()->id);
    }

    public function test_scope_last_week_filters_correctly(): void
    {
        $oldScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(10)->create();
        $recentScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(3)->create();

        $results = PriceScrape::lastWeek()->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_last_month_filters_correctly(): void
    {
        $oldScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(45)->create();
        $recentScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(15)->create();

        $results = PriceScrape::lastMonth()->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_today_filters_correctly(): void
    {
        $yesterdayScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(1)->create();
        $todayScrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedToday()->create();

        $results = PriceScrape::today()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($todayScrape->id, $results->first()->id);
    }

    // =====================
    // Competitor Scope Tests
    // =====================

    public function test_scope_for_competitor_filters_correctly(): void
    {
        PriceScrape::factory()->forProduct($this->product)->forCompetitor('Takealot')->count(3)->create();
        PriceScrape::factory()->forProduct($this->product)->forCompetitor('Clicks')->count(2)->create();

        $this->assertCount(3, PriceScrape::forCompetitor('Takealot')->get());
        $this->assertCount(2, PriceScrape::forCompetitor('Clicks')->get());
    }

    public function test_scope_for_product_filters_correctly(): void
    {
        $entityType = EntityType::firstOrCreate(
            ['name' => 'other_product'],
            ['display_name' => 'Other Product', 'description' => 'Another product type']
        );
        $otherProduct = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        PriceScrape::factory()->forProduct($this->product)->count(3)->create();
        PriceScrape::factory()->forProduct($otherProduct)->count(2)->create();

        $this->assertCount(3, PriceScrape::forProduct($this->product->id)->get());
        $this->assertCount(2, PriceScrape::forProduct($otherProduct->id)->get());
    }

    public function test_scope_in_stock_filters_correctly(): void
    {
        PriceScrape::factory()->forProduct($this->product)->inStock()->count(3)->create();
        PriceScrape::factory()->forProduct($this->product)->outOfStock()->count(2)->create();

        $this->assertCount(3, PriceScrape::inStock()->get());
    }

    public function test_scope_out_of_stock_filters_correctly(): void
    {
        PriceScrape::factory()->forProduct($this->product)->inStock()->count(3)->create();
        PriceScrape::factory()->forProduct($this->product)->outOfStock()->count(2)->create();

        $this->assertCount(2, PriceScrape::outOfStock()->get());
    }

    public function test_scope_most_recent_orders_by_scraped_at_descending(): void
    {
        $oldest = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(10)->create();
        $middle = PriceScrape::factory()->forProduct($this->product)
            ->scrapedDaysAgo(5)->create();
        $newest = PriceScrape::factory()->forProduct($this->product)
            ->scrapedToday()->create();

        // Filter by product to isolate these test scrapes, then apply mostRecent scope
        $results = PriceScrape::forProduct($this->product->id)->mostRecent()->get();

        $this->assertEquals($newest->id, $results->first()->id);
        $this->assertEquals($oldest->id, $results->last()->id);
    }

    // =====================
    // Price Change Detection Tests
    // =====================

    public function test_get_previous_scrape_returns_correct_scrape(): void
    {
        $competitor = 'Takealot';

        $first = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->scrapedDaysAgo(10)
            ->create();

        $second = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->scrapedDaysAgo(5)
            ->create();

        $third = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->scrapedToday()
            ->create();

        $this->assertEquals($second->id, $third->getPreviousScrape()->id);
        $this->assertEquals($first->id, $second->getPreviousScrape()->id);
        $this->assertNull($first->getPreviousScrape());
    }

    public function test_get_previous_scrape_respects_competitor(): void
    {
        $takealotOld = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->scrapedDaysAgo(10)
            ->create();

        $clicksNew = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->scrapedDaysAgo(5)
            ->create();

        $takealotNew = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->scrapedToday()
            ->create();

        // Should get takealotOld, not clicksNew
        $this->assertEquals($takealotOld->id, $takealotNew->getPreviousScrape()->id);
    }

    public function test_get_price_change_returns_correct_value(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        $current = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(120.00)
            ->scrapedToday()
            ->create();

        $this->assertEquals(20.00, $current->getPriceChange());
    }

    public function test_get_price_change_returns_negative_for_decrease(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        $current = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(80.00)
            ->scrapedToday()
            ->create();

        $this->assertEquals(-20.00, $current->getPriceChange());
    }

    public function test_get_price_change_returns_null_for_first_scrape(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create();

        $this->assertNull($scrape->getPriceChange());
    }

    public function test_get_price_change_percent_returns_correct_value(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        $current = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(125.00)
            ->scrapedToday()
            ->create();

        $this->assertEquals(25.00, $current->getPriceChangePercent());
    }

    public function test_get_price_change_percent_returns_negative_for_decrease(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        $current = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(75.00)
            ->scrapedToday()
            ->create();

        $this->assertEquals(-25.00, $current->getPriceChangePercent());
    }

    public function test_get_price_change_percent_returns_null_for_zero_previous_price(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(0.00)
            ->scrapedDaysAgo(5)
            ->create();

        $current = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedToday()
            ->create();

        $this->assertNull($current->getPriceChangePercent());
    }

    public function test_has_price_increased_returns_true_for_increase(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        $current = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(150.00)
            ->scrapedToday()
            ->create();

        $this->assertTrue($current->hasPriceIncreased());
        $this->assertFalse($current->hasPriceDecreased());
    }

    public function test_has_price_decreased_returns_true_for_decrease(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        $current = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(50.00)
            ->scrapedToday()
            ->create();

        $this->assertTrue($current->hasPriceDecreased());
        $this->assertFalse($current->hasPriceIncreased());
    }

    public function test_has_price_changed_by_percent_threshold(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(5)
            ->create();

        $smallChange = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(105.00) // 5% increase
            ->scrapedDaysAgo(2)
            ->create();

        $bigChange = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(125.00) // ~19% from 105
            ->scrapedToday()
            ->create();

        $this->assertFalse($smallChange->hasPriceChangedByPercent(10));
        $this->assertTrue($smallChange->hasPriceChangedByPercent(5));
        $this->assertTrue($bigChange->hasPriceChangedByPercent(10));
    }

    public function test_has_stock_status_changed(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->inStock()
            ->scrapedDaysAgo(5)
            ->create();

        $stillInStock = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->inStock()
            ->scrapedDaysAgo(2)
            ->create();

        $nowOutOfStock = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->outOfStock()
            ->scrapedToday()
            ->create();

        $this->assertFalse($stillInStock->hasStockStatusChanged());
        $this->assertTrue($nowOutOfStock->hasStockStatusChanged());
    }

    // =====================
    // Aggregation Method Tests
    // =====================

    public function test_get_price_history_returns_ordered_collection(): void
    {
        $competitor = 'Takealot';

        $oldest = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(100.00)
            ->scrapedDaysAgo(20)
            ->create();

        $middle = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(110.00)
            ->scrapedDaysAgo(10)
            ->create();

        $newest = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->withPrice(120.00)
            ->scrapedToday()
            ->create();

        $history = PriceScrape::getPriceHistory($this->product->id, $competitor);

        $this->assertCount(3, $history);
        // Should be ordered ascending by scraped_at
        $this->assertEquals(100.00, $history->first()->price);
        $this->assertEquals(120.00, $history->last()->price);
    }

    public function test_get_price_history_respects_date_range(): void
    {
        $competitor = 'Takealot';

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->scrapedAt(Carbon::parse('2024-01-01'))
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->scrapedAt(Carbon::parse('2024-06-15'))
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor($competitor)
            ->scrapedAt(Carbon::parse('2024-12-01'))
            ->create();

        $history = PriceScrape::getPriceHistory(
            $this->product->id,
            $competitor,
            Carbon::parse('2024-05-01'),
            Carbon::parse('2024-07-01')
        );

        $this->assertCount(1, $history);
    }

    public function test_get_latest_competitor_prices_returns_most_recent(): void
    {
        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(100.00)
            ->scrapedDaysAgo(10)
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(110.00)
            ->scrapedToday()
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->withPrice(95.00)
            ->scrapedToday()
            ->create();

        $latestPrices = PriceScrape::getLatestCompetitorPrices($this->product->id);

        $this->assertCount(2, $latestPrices);

        $takealotPrice = $latestPrices->firstWhere('competitor_name', 'Takealot');
        $clicksPrice = $latestPrices->firstWhere('competitor_name', 'Clicks');

        $this->assertEquals(110.00, $takealotPrice->price);
        $this->assertEquals(95.00, $clicksPrice->price);
    }

    public function test_get_competitors_returns_unique_names(): void
    {
        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->count(3)
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->count(2)
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Amazon')
            ->count(1)
            ->create();

        $competitors = PriceScrape::getCompetitors();

        $this->assertCount(3, $competitors);
        $this->assertContains('Takealot', $competitors);
        $this->assertContains('Clicks', $competitors);
        $this->assertContains('Amazon', $competitors);
    }

    public function test_get_average_price_calculates_correctly(): void
    {
        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(100.00)
            ->scrapedToday()
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->withPrice(120.00)
            ->scrapedToday()
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Amazon')
            ->withPrice(110.00)
            ->scrapedToday()
            ->create();

        $avgPrice = PriceScrape::getAveragePrice($this->product->id);

        $this->assertEquals(110.00, $avgPrice);
    }

    public function test_get_average_price_returns_null_for_no_data(): void
    {
        $avgPrice = PriceScrape::getAveragePrice($this->product->id);

        $this->assertNull($avgPrice);
    }

    public function test_get_lowest_price_returns_cheapest(): void
    {
        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(100.00)
            ->scrapedToday()
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->withPrice(85.00)
            ->scrapedToday()
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Amazon')
            ->withPrice(110.00)
            ->scrapedToday()
            ->create();

        $lowest = PriceScrape::getLowestPrice($this->product->id);

        $this->assertEquals('Clicks', $lowest->competitor_name);
        $this->assertEquals(85.00, $lowest->price);
    }

    public function test_get_highest_price_returns_most_expensive(): void
    {
        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->withPrice(100.00)
            ->scrapedToday()
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Clicks')
            ->withPrice(85.00)
            ->scrapedToday()
            ->create();

        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Amazon')
            ->withPrice(110.00)
            ->scrapedToday()
            ->create();

        $highest = PriceScrape::getHighestPrice($this->product->id);

        $this->assertEquals('Amazon', $highest->competitor_name);
        $this->assertEquals(110.00, $highest->price);
    }

    // =====================
    // Utility Method Tests
    // =====================

    public function test_get_formatted_price_with_zar(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->withPrice(99.99)
            ->withCurrency('ZAR')
            ->create();

        $this->assertEquals('R99.99', $scrape->getFormattedPrice());
    }

    public function test_get_formatted_price_with_usd(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->withPrice(49.99)
            ->withCurrency('USD')
            ->create();

        $this->assertEquals('$49.99', $scrape->getFormattedPrice());
    }

    public function test_get_formatted_price_with_unknown_currency(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->withPrice(99.99)
            ->withCurrency('JPY')
            ->create();

        $this->assertEquals('JPY 99.99', $scrape->getFormattedPrice());
    }

    public function test_get_time_since_scrape_returns_human_readable(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->scrapedAt(now()->subHours(2))
            ->create();

        $this->assertStringContainsString('ago', $scrape->getTimeSinceScrape());
    }

    // =====================
    // Index Tests
    // =====================

    public function test_queries_use_product_id_index(): void
    {
        // Create test data
        PriceScrape::factory()->forProduct($this->product)->count(10)->create();

        // This test ensures the query doesn't fail - in a real scenario
        // you'd check the query plan with EXPLAIN
        $results = PriceScrape::forProduct($this->product->id)->get();

        $this->assertCount(10, $results);
    }

    public function test_queries_use_competitor_name_index(): void
    {
        PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Takealot')
            ->count(10)
            ->create();

        $results = PriceScrape::forCompetitor('Takealot')->get();

        $this->assertCount(10, $results);
    }

    // =====================
    // Factory State Tests
    // =====================

    public function test_factory_creates_valid_data(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create();

        $this->assertNotNull($scrape->product_id);
        $this->assertNotNull($scrape->competitor_name);
        $this->assertNotNull($scrape->price);
        $this->assertNotNull($scrape->currency);
        $this->assertNotNull($scrape->scraped_at);
    }

    public function test_factory_for_product_state(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)->create();

        $this->assertEquals($this->product->id, $scrape->product_id);
    }

    public function test_factory_for_competitor_state(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->forCompetitor('Test Competitor')
            ->create();

        $this->assertEquals('Test Competitor', $scrape->competitor_name);
    }

    public function test_factory_with_price_state(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->withPrice(123.45)
            ->create();

        $this->assertEquals(123.45, $scrape->price);
    }

    public function test_factory_in_stock_state(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->inStock()
            ->create();

        $this->assertTrue($scrape->in_stock);
    }

    public function test_factory_out_of_stock_state(): void
    {
        $scrape = PriceScrape::factory()->forProduct($this->product)
            ->outOfStock()
            ->create();

        $this->assertFalse($scrape->in_stock);
    }
}
