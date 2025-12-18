<?php

namespace App\Filament\PricingPanel\Components;

use App\Notifications\PriceAlertTriggered;
use App\Services\PriceAlertNotificationService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBadge extends Component
{
    /**
     * Whether the dropdown is open.
     */
    public bool $isOpen = false;

    /**
     * Get the notification service.
     */
    protected function getNotificationService(): PriceAlertNotificationService
    {
        return app(PriceAlertNotificationService::class);
    }

    /**
     * Get unread notification count.
     */
    #[Computed]
    public function unreadCount(): int
    {
        $user = auth()->user();

        if (! $user) {
            return 0;
        }

        return $this->getNotificationService()->getUnreadCount($user);
    }

    /**
     * Get recent notifications.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function notifications(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return $user->notifications()
            ->where('type', PriceAlertTriggered::class)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Price Alert',
                'message' => $n->data['message'] ?? '',
                'icon' => $n->data['icon'] ?? 'heroicon-o-bell',
                'color' => $n->data['color'] ?? 'primary',
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->diffForHumans(),
                'product_name' => $n->data['product_name'] ?? null,
                'competitor_name' => $n->data['competitor_name'] ?? null,
                'competitor_price' => $n->data['competitor_price'] ?? null,
            ])
            ->toArray();
    }

    /**
     * Toggle dropdown visibility.
     */
    public function toggleDropdown(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    /**
     * Close the dropdown.
     */
    public function closeDropdown(): void
    {
        $this->isOpen = false;
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(string $notificationId): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $notification = $user->notifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();
            unset($this->unreadCount);
            unset($this->notifications);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $count = $this->getNotificationService()->markAllAsRead($user);

        if ($count > 0) {
            Notification::make()
                ->title('All notifications marked as read')
                ->success()
                ->send();
        }

        unset($this->unreadCount);
        unset($this->notifications);
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(string $notificationId): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $notification = $user->notifications()->find($notificationId);

        if ($notification) {
            $notification->delete();
            unset($this->unreadCount);
            unset($this->notifications);
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('filament.pricing-panel.components.notification-badge');
    }
}
