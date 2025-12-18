<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use App\Notifications\PriceAlertTriggered;
use App\Services\PriceAlertNotificationService;
use App\Services\PriceAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PriceAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Entity $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create or get a product entity type for tests
        $entityType = EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Products', 'description' => 'Product entity type']
        );
        $this->product = Entity::factory()->create(['entity_type_id' => $entityType->id]);
    }

    /**
     * Helper to create an alert with the test product.
     */
    protected function createAlertWithProduct(array $attributes = []): PriceAlert
    {
        return PriceAlert::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ], $attributes));
    }

    /**
     * Helper to create a scrape for the test product.
     */
    protected function createScrapeForProduct(array $attributes = []): PriceScrape
    {
        return PriceScrape::factory()->create(array_merge([
            'product_id' => $this->product->id,
        ], $attributes));
    }

    // =====================
    // PriceAlertTriggered Notification Tests
    // =====================

    public function test_notification_can_be_created(): void
    {
        $alert = $this->createAlertWithProduct([
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
        ]);

        $scrape = $this->createScrapeForProduct([
            'competitor_name' => 'Takealot',
            'price' => 89.99,
        ]);

        $notification = new PriceAlertTriggered($alert, $scrape, 110.00);

        $this->assertInstanceOf(PriceAlertTriggered::class, $notification);
        $this->assertSame($alert->id, $notification->alert->id);
        $this->assertSame($scrape->id, $notification->scrape->id);
        $this->assertEquals(110.00, $notification->ourPrice);
    }

    public function test_notification_has_correct_channels(): void
    {
        $alert = $this->createAlertWithProduct([
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
        ]);

        $scrape = $this->createScrapeForProduct();

        $notification = new PriceAlertTriggered($alert, $scrape);

        $channels = $notification->via($this->user);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    public function test_notification_to_array_contains_required_data(): void
    {
        $alert = $this->createAlertWithProduct([
            'alert_type' => PriceAlert::TYPE_COMPETITOR_BEATS,
        ]);

        $scrape = $this->createScrapeForProduct([
            'competitor_name' => 'Checkers',
            'price' => 85.00,
            'in_stock' => true,
        ]);

        $notification = new PriceAlertTriggered($alert, $scrape, 100.00);
        $data = $notification->toArray($this->user);

        $this->assertArrayHasKey('alert_id', $data);
        $this->assertArrayHasKey('alert_type', $data);
        $this->assertArrayHasKey('competitor_name', $data);
        $this->assertArrayHasKey('competitor_price', $data);
        $this->assertArrayHasKey('our_price', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('icon', $data);
        $this->assertArrayHasKey('color', $data);

        $this->assertEquals($alert->id, $data['alert_id']);
        $this->assertEquals('competitor_beats', $data['alert_type']);
        $this->assertEquals('Checkers', $data['competitor_name']);
        $this->assertEquals(85.00, $data['competitor_price']);
        $this->assertEquals(100.00, $data['our_price']);
    }

    public function test_notification_mail_has_correct_subject_for_price_below(): void
    {
        $alert = $this->createAlertWithProduct([
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100,
        ]);

        $scrape = $this->createScrapeForProduct([
            'competitor_name' => 'Takealot',
            'price' => 85.00,
        ]);

        $notification = new PriceAlertTriggered($alert, $scrape);
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('price dropped', $mail->subject);
    }

    public function test_notification_mail_has_correct_subject_for_competitor_beats(): void
    {
        $alert = $this->createAlertWithProduct([
            'alert_type' => PriceAlert::TYPE_COMPETITOR_BEATS,
        ]);

        $scrape = $this->createScrapeForProduct([
            'competitor_name' => 'Takealot',
            'price' => 85.00,
        ]);

        $notification = new PriceAlertTriggered($alert, $scrape, 100.00);
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('Competitor beating', $mail->subject);
    }

    public function test_notification_has_correct_icon_for_each_type(): void
    {
        $types = [
            PriceAlert::TYPE_PRICE_BELOW => 'heroicon-o-arrow-trending-down',
            PriceAlert::TYPE_COMPETITOR_BEATS => 'heroicon-o-exclamation-triangle',
            PriceAlert::TYPE_PRICE_CHANGE => 'heroicon-o-currency-dollar',
            PriceAlert::TYPE_OUT_OF_STOCK => 'heroicon-o-x-circle',
        ];

        foreach ($types as $type => $expectedIcon) {
            $alert = $this->createAlertWithProduct(['alert_type' => $type]);
            $scrape = $this->createScrapeForProduct();

            $notification = new PriceAlertTriggered($alert, $scrape);
            $data = $notification->toArray($this->user);

            $this->assertEquals($expectedIcon, $data['icon'], "Icon mismatch for type {$type}");
        }
    }

    public function test_notification_has_correct_color_for_each_type(): void
    {
        $types = [
            PriceAlert::TYPE_PRICE_BELOW => 'success',
            PriceAlert::TYPE_COMPETITOR_BEATS => 'danger',
            PriceAlert::TYPE_PRICE_CHANGE => 'warning',
            PriceAlert::TYPE_OUT_OF_STOCK => 'info',
        ];

        foreach ($types as $type => $expectedColor) {
            $alert = $this->createAlertWithProduct(['alert_type' => $type]);
            $scrape = $this->createScrapeForProduct();

            $notification = new PriceAlertTriggered($alert, $scrape);
            $data = $notification->toArray($this->user);

            $this->assertEquals($expectedColor, $data['color'], "Color mismatch for type {$type}");
        }
    }

    // =====================
    // PriceAlertNotificationService Tests
    // =====================

    public function test_notification_service_sends_notification(): void
    {
        Notification::fake();

        $alert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100,
        ]);

        $scrape = PriceScrape::factory()->create([
            'product_id' => $this->product->id,
            'price' => 85.00,
        ]);

        $service = new PriceAlertNotificationService;
        $service->sendAlertNotification($alert, $scrape, 110.00);

        Notification::assertSentTo(
            $this->user,
            PriceAlertTriggered::class
        );
    }

    public function test_notification_service_sends_bulk_notifications(): void
    {
        Notification::fake();

        $alerts = collect();

        for ($i = 0; $i < 3; $i++) {
            $alert = PriceAlert::factory()->create([
                'user_id' => $this->user->id,
                'product_id' => $this->product->id,
                'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            ]);

            $scrape = PriceScrape::factory()->create([
                'product_id' => $this->product->id,
            ]);

            $alerts->push([
                'alert' => $alert,
                'scrape' => $scrape,
                'our_price' => 100.00,
            ]);
        }

        $service = new PriceAlertNotificationService;
        $count = $service->sendBulkAlertNotifications($alerts);

        $this->assertEquals(3, $count);

        Notification::assertSentToTimes($this->user, PriceAlertTriggered::class, 3);
    }

    public function test_notification_service_does_not_send_for_user_without_relationship(): void
    {
        Notification::fake();

        // Create alert for a valid user first, then check behavior when user_id doesn't exist
        $alert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
        ]);

        // Manually set user_id to a non-existent ID (bypassing FK constraint)
        $alert->user_id = 99999;

        $scrape = PriceScrape::factory()->create([
            'product_id' => $this->product->id,
        ]);

        $service = new PriceAlertNotificationService;
        $service->sendAlertNotification($alert, $scrape);

        Notification::assertNothingSent();
    }

    public function test_notification_service_get_unread_count(): void
    {
        // Create some notifications
        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(),
            $this->createScrapeForProduct()
        ));

        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(),
            $this->createScrapeForProduct()
        ));

        $service = new PriceAlertNotificationService;
        $count = $service->getUnreadCount($this->user);

        $this->assertEquals(2, $count);
    }

    public function test_notification_service_mark_all_as_read(): void
    {
        // Create some notifications
        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(),
            $this->createScrapeForProduct()
        ));

        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(),
            $this->createScrapeForProduct()
        ));

        $service = new PriceAlertNotificationService;

        $this->assertEquals(2, $service->getUnreadCount($this->user));

        $count = $service->markAllAsRead($this->user);

        $this->assertEquals(2, $count);
        $this->assertEquals(0, $service->getUnreadCount($this->user));
    }

    public function test_notification_service_get_notifications_summary(): void
    {
        // Create notifications of different types
        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(['alert_type' => PriceAlert::TYPE_PRICE_BELOW]),
            $this->createScrapeForProduct()
        ));

        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(['alert_type' => PriceAlert::TYPE_COMPETITOR_BEATS]),
            $this->createScrapeForProduct()
        ));

        $service = new PriceAlertNotificationService;
        $summary = $service->getNotificationsSummary($this->user);

        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('unread', $summary);
        $this->assertArrayHasKey('today', $summary);
        $this->assertArrayHasKey('by_type', $summary);
        $this->assertArrayHasKey('recent', $summary);

        $this->assertEquals(2, $summary['total']);
        $this->assertEquals(2, $summary['unread']);
    }

    public function test_notification_service_delete_old_notifications(): void
    {
        // Create an old notification
        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(),
            $this->createScrapeForProduct()
        ));

        // Age the notification
        DatabaseNotification::where('notifiable_id', $this->user->id)
            ->update(['created_at' => now()->subDays(60)]);

        // Create a recent notification
        $this->user->notify(new PriceAlertTriggered(
            $this->createAlertWithProduct(),
            $this->createScrapeForProduct()
        ));

        $service = new PriceAlertNotificationService;

        // Should have 2 notifications
        $this->assertEquals(2, $service->getAllNotifications($this->user)->count());

        // Delete notifications older than 30 days
        $deleted = $service->deleteOldNotifications($this->user, 30);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, $service->getAllNotifications($this->user)->count());
    }

    // =====================
    // PriceAlertService Integration Tests
    // =====================

    public function test_price_alert_service_sends_notification_when_alert_triggers(): void
    {
        Notification::fake();

        $alert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'competitor_name' => null, // Match any competitor
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
            'is_active' => true,
        ]);

        $scrape = PriceScrape::factory()->create([
            'product_id' => $this->product->id,
            'price' => 85.00,
        ]);

        $service = new PriceAlertService;
        $triggered = $service->processScrape($scrape);

        $this->assertCount(1, $triggered);

        Notification::assertSentTo(
            $this->user,
            PriceAlertTriggered::class,
            function (PriceAlertTriggered $notification) use ($alert, $scrape) {
                return $notification->alert->id === $alert->id
                    && $notification->scrape->id === $scrape->id;
            }
        );
    }

    public function test_price_alert_service_can_disable_notifications(): void
    {
        Notification::fake();

        $alert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'competitor_name' => null, // Match any competitor
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
            'is_active' => true,
        ]);

        $scrape = PriceScrape::factory()->create([
            'product_id' => $this->product->id,
            'price' => 85.00,
        ]);

        $service = (new PriceAlertService)->withoutNotifications();
        $triggered = $service->processScrape($scrape);

        $this->assertCount(1, $triggered);

        Notification::assertNothingSent();
    }

    public function test_price_alert_service_multiple_alerts_same_scrape(): void
    {
        Notification::fake();

        // Create first alert for the product
        $firstAlert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'competitor_name' => null, // Match any competitor
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
            'is_active' => true,
        ]);

        // Create additional alerts for the same product
        for ($i = 1; $i < 3; $i++) {
            PriceAlert::factory()->create([
                'user_id' => $this->user->id,
                'product_id' => $this->product->id,
                'competitor_name' => null, // Match any competitor
                'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
                'threshold' => 100.00 + ($i * 10),
                'is_active' => true,
            ]);
        }

        $scrape = PriceScrape::factory()->create([
            'product_id' => $this->product->id,
            'price' => 85.00, // Below all thresholds
        ]);

        $service = new PriceAlertService;
        $triggered = $service->processScrape($scrape);

        $this->assertCount(3, $triggered);

        Notification::assertSentToTimes($this->user, PriceAlertTriggered::class, 3);
    }

    public function test_price_alert_service_with_custom_notification_service(): void
    {
        $mockService = $this->mock(PriceAlertNotificationService::class);
        $mockService->shouldReceive('sendAlertNotification')
            ->once();

        $alert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'competitor_name' => null, // Match any competitor
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => 100.00,
            'is_active' => true,
        ]);

        $scrape = PriceScrape::factory()->create([
            'product_id' => $this->product->id,
            'price' => 85.00,
        ]);

        $service = (new PriceAlertService)->setNotificationService($mockService);
        $service->processScrape($scrape);
    }
}
