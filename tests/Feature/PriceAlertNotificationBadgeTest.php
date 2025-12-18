<?php

namespace Tests\Feature;

use App\Filament\PricingPanel\Components\NotificationBadge;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use App\Notifications\PriceAlertTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriceAlertNotificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required roles
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('pricing-analyst', 'web');

        $this->user = User::factory()->create();
        $this->user->assignRole('pricing-analyst');
    }

    public function test_notification_badge_shows_zero_when_no_notifications(): void
    {
        $this->actingAs($this->user);

        Livewire::test(NotificationBadge::class)
            ->assertSet('unreadCount', 0)
            ->assertSet('notifications', []);
    }

    public function test_notification_badge_shows_correct_unread_count(): void
    {
        $this->actingAs($this->user);

        // Create some notifications
        for ($i = 0; $i < 5; $i++) {
            $this->user->notify(new PriceAlertTriggered(
                PriceAlert::factory()->create(['user_id' => $this->user->id]),
                PriceScrape::factory()->create()
            ));
        }

        Livewire::test(NotificationBadge::class)
            ->assertSet('unreadCount', 5);
    }

    public function test_notification_badge_shows_notifications_list(): void
    {
        $this->actingAs($this->user);

        $alert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
        ]);

        $scrape = PriceScrape::factory()->create([
            'competitor_name' => 'Test Competitor',
            'price' => 99.99,
        ]);

        $this->user->notify(new PriceAlertTriggered($alert, $scrape));

        $component = Livewire::test(NotificationBadge::class);

        $notifications = $component->get('notifications');

        $this->assertCount(1, $notifications);
        $this->assertEquals('Test Competitor', $notifications[0]['competitor_name']);
        $this->assertEquals(99.99, $notifications[0]['competitor_price']);
    }

    public function test_notification_badge_can_mark_notification_as_read(): void
    {
        $this->actingAs($this->user);

        $this->user->notify(new PriceAlertTriggered(
            PriceAlert::factory()->create(['user_id' => $this->user->id]),
            PriceScrape::factory()->create()
        ));

        $notification = $this->user->notifications()->first();
        $this->assertNull($notification->read_at);

        Livewire::test(NotificationBadge::class)
            ->call('markAsRead', $notification->id);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_notification_badge_can_mark_all_as_read(): void
    {
        $this->actingAs($this->user);

        // Create multiple notifications
        for ($i = 0; $i < 3; $i++) {
            $this->user->notify(new PriceAlertTriggered(
                PriceAlert::factory()->create(['user_id' => $this->user->id]),
                PriceScrape::factory()->create()
            ));
        }

        $this->assertEquals(3, $this->user->unreadNotifications()->count());

        Livewire::test(NotificationBadge::class)
            ->call('markAllAsRead');

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    public function test_notification_badge_can_delete_notification(): void
    {
        $this->actingAs($this->user);

        $this->user->notify(new PriceAlertTriggered(
            PriceAlert::factory()->create(['user_id' => $this->user->id]),
            PriceScrape::factory()->create()
        ));

        $notification = $this->user->notifications()->first();

        Livewire::test(NotificationBadge::class)
            ->call('deleteNotification', $notification->id);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_notification_badge_limits_notifications_to_ten(): void
    {
        $this->actingAs($this->user);

        // Create 15 notifications
        for ($i = 0; $i < 15; $i++) {
            $this->user->notify(new PriceAlertTriggered(
                PriceAlert::factory()->create(['user_id' => $this->user->id]),
                PriceScrape::factory()->create()
            ));
        }

        $component = Livewire::test(NotificationBadge::class);

        $notifications = $component->get('notifications');

        $this->assertCount(10, $notifications);
        $this->assertEquals(15, $component->get('unreadCount'));
    }

    public function test_notification_badge_shows_read_status_correctly(): void
    {
        $this->actingAs($this->user);

        // Create unread notification
        $this->user->notify(new PriceAlertTriggered(
            PriceAlert::factory()->create(['user_id' => $this->user->id]),
            PriceScrape::factory()->create()
        ));

        // Create read notification
        $this->user->notify(new PriceAlertTriggered(
            PriceAlert::factory()->create(['user_id' => $this->user->id]),
            PriceScrape::factory()->create()
        ));

        // Mark second one as read
        $this->user->notifications()->latest()->first()->markAsRead();

        $component = Livewire::test(NotificationBadge::class);
        $notifications = $component->get('notifications');

        // Most recent (read) should be first
        $this->assertTrue($notifications[0]['read']);
        $this->assertFalse($notifications[1]['read']);
    }

    public function test_notification_badge_handles_guest_user(): void
    {
        // Don't act as any user

        Livewire::test(NotificationBadge::class)
            ->assertSet('unreadCount', 0)
            ->assertSet('notifications', []);
    }

    public function test_notification_badge_shows_correct_notification_data(): void
    {
        $this->actingAs($this->user);

        $alert = PriceAlert::factory()->create([
            'user_id' => $this->user->id,
            'alert_type' => PriceAlert::TYPE_COMPETITOR_BEATS,
        ]);

        $scrape = PriceScrape::factory()->create([
            'competitor_name' => 'Checkers',
            'price' => 79.99,
        ]);

        $this->user->notify(new PriceAlertTriggered($alert, $scrape, 89.99));

        $component = Livewire::test(NotificationBadge::class);
        $notifications = $component->get('notifications');

        $this->assertEquals('Competitor Price Alert', $notifications[0]['title']);
        $this->assertEquals('danger', $notifications[0]['color']);
        $this->assertEquals('heroicon-o-exclamation-triangle', $notifications[0]['icon']);
    }

    public function test_notification_badge_dropdown_toggle(): void
    {
        $this->actingAs($this->user);

        Livewire::test(NotificationBadge::class)
            ->assertSet('isOpen', false)
            ->call('toggleDropdown')
            ->assertSet('isOpen', true)
            ->call('toggleDropdown')
            ->assertSet('isOpen', false);
    }

    public function test_notification_badge_close_dropdown(): void
    {
        $this->actingAs($this->user);

        Livewire::test(NotificationBadge::class)
            ->set('isOpen', true)
            ->call('closeDropdown')
            ->assertSet('isOpen', false);
    }
}
