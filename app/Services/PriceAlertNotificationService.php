<?php

namespace App\Services;

use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use App\Notifications\PriceAlertTriggered;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class PriceAlertNotificationService
{
    /**
     * Send notification for a triggered price alert.
     */
    public function sendAlertNotification(
        PriceAlert $alert,
        PriceScrape $scrape,
        ?float $ourPrice = null
    ): void {
        /** @var User|null $user */
        $user = $alert->user;

        if ($user === null) {
            return;
        }

        $user->notify(new PriceAlertTriggered($alert, $scrape, $ourPrice));
    }

    /**
     * Send notifications for multiple triggered alerts.
     *
     * @param  Collection<int, array{alert: PriceAlert, scrape: PriceScrape, our_price?: float}>  $triggeredAlerts
     */
    public function sendBulkAlertNotifications(Collection $triggeredAlerts): int
    {
        $count = 0;

        foreach ($triggeredAlerts as $item) {
            $this->sendAlertNotification(
                $item['alert'],
                $item['scrape'],
                $item['our_price'] ?? null
            );
            $count++;
        }

        return $count;
    }

    /**
     * Get unread price alert notifications for a user.
     *
     * @return EloquentCollection<int, DatabaseNotification>
     */
    public function getUnreadNotifications(User $user, int $limit = 50): EloquentCollection
    {
        return $user->unreadNotifications()
            ->where('type', PriceAlertTriggered::class)
            ->limit($limit)
            ->get();
    }

    /**
     * Get all price alert notifications for a user.
     *
     * @return EloquentCollection<int, DatabaseNotification>
     */
    public function getAllNotifications(User $user, int $limit = 100): EloquentCollection
    {
        return $user->notifications()
            ->where('type', PriceAlertTriggered::class)
            ->limit($limit)
            ->get();
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()
            ->where('type', PriceAlertTriggered::class)
            ->count();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all price alert notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        $count = $user->unreadNotifications()
            ->where('type', PriceAlertTriggered::class)
            ->count();

        $user->unreadNotifications()
            ->where('type', PriceAlertTriggered::class)
            ->update(['read_at' => now()]);

        return $count;
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(DatabaseNotification $notification): bool
    {
        return $notification->delete();
    }

    /**
     * Delete old notifications for a user.
     *
     * @param  int  $daysOld  Delete notifications older than this many days
     */
    public function deleteOldNotifications(User $user, int $daysOld = 30): int
    {
        return $user->notifications()
            ->where('type', PriceAlertTriggered::class)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Get notifications grouped by alert type.
     *
     * @return Collection<string, Collection<int, DatabaseNotification>>
     */
    public function getNotificationsGroupedByType(User $user): Collection
    {
        $notifications = $user->notifications()
            ->where('type', PriceAlertTriggered::class)
            ->get();

        /** @var Collection<string, Collection<int, DatabaseNotification>> $grouped */
        $grouped = $notifications->groupBy(function (DatabaseNotification $n): string {
            return (string) ($n->data['alert_type'] ?? 'unknown');
        });

        return $grouped;
    }

    /**
     * Get recent notifications summary for dashboard display.
     *
     * @return array<string, mixed>
     */
    public function getNotificationsSummary(User $user): array
    {
        $notifications = $user->notifications()
            ->where('type', PriceAlertTriggered::class)
            ->get();

        $unread = $notifications->whereNull('read_at');
        $today = $notifications->filter(
            fn (DatabaseNotification $n): bool => $n->created_at?->isToday() ?? false
        );

        /** @var Collection<string, Collection<int, DatabaseNotification>> $byType */
        $byType = $notifications->groupBy(function (DatabaseNotification $n): string {
            return (string) ($n->data['alert_type'] ?? 'unknown');
        });

        return [
            'total' => $notifications->count(),
            'unread' => $unread->count(),
            'today' => $today->count(),
            'by_type' => $byType->map->count()->toArray(),
            'recent' => $notifications->take(5)->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Price Alert',
                'message' => $n->data['message'] ?? '',
                'icon' => $n->data['icon'] ?? 'heroicon-o-bell',
                'color' => $n->data['color'] ?? 'primary',
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->diffForHumans(),
            ])->toArray(),
        ];
    }

    /**
     * Check if user should receive notifications (based on preferences).
     * Can be extended to check user notification preferences.
     */
    public function shouldNotifyUser(User $user, string $alertType): bool
    {
        // For now, always return true
        // Future: Check user preferences for notification settings
        return true;
    }
}
