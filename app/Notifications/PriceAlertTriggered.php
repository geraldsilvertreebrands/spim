<?php

namespace App\Notifications;

use App\Models\PriceAlert;
use App\Models\PriceScrape;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceAlertTriggered extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PriceAlert $alert,
        public PriceScrape $scrape,
        public ?float $ourPrice = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->getEmailSubject())
            ->greeting('Price Alert Triggered!')
            ->line($this->getAlertDescription());

        // Add details
        if ($this->alert->product) {
            $productName = $this->alert->product->getAttributeValue('name') ?? 'Unknown Product';
            $message->line("**Product:** {$productName}");
        }

        $scrapePrice = (float) $this->scrape->price;

        $message->line("**Competitor:** {$this->scrape->competitor_name}")
            ->line('**Competitor Price:** R'.number_format($scrapePrice, 2));

        if ($this->ourPrice !== null) {
            $message->line('**Our Price:** R'.number_format($this->ourPrice, 2));
            $difference = $this->ourPrice - $scrapePrice;
            $differenceLabel = $difference > 0 ? 'higher' : 'lower';
            $message->line('**Difference:** R'.number_format(abs($difference), 2)." ({$differenceLabel})");
        }

        if (! $this->scrape->in_stock) {
            $message->line('**Stock Status:** Out of Stock');
        }

        $message->action('View Pricing Dashboard', url('/pricing'))
            ->line('You can manage your price alerts in the Pricing Panel.');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'alert_type' => $this->alert->alert_type,
            'alert_description' => $this->alert->getDescription(),
            'product_id' => $this->scrape->product_id,
            'product_name' => $this->alert->product?->getAttributeValue('name'),
            'competitor_name' => $this->scrape->competitor_name,
            'competitor_price' => (float) $this->scrape->price,
            'our_price' => $this->ourPrice,
            'in_stock' => $this->scrape->in_stock,
            'scraped_at' => $this->scrape->scraped_at->toIso8601String(),
            'title' => $this->getNotificationTitle(),
            'message' => $this->getAlertDescription(),
            'icon' => $this->getAlertIcon(),
            'color' => $this->getAlertColor(),
        ];
    }

    /**
     * Get the email subject based on alert type.
     */
    protected function getEmailSubject(): string
    {
        $productName = $this->alert->product?->getAttributeValue('name') ?? 'Product';

        return match ($this->alert->alert_type) {
            PriceAlert::TYPE_PRICE_BELOW => "Price Alert: {$productName} price dropped!",
            PriceAlert::TYPE_COMPETITOR_BEATS => "Price Alert: Competitor beating your price on {$productName}",
            PriceAlert::TYPE_PRICE_CHANGE => "Price Alert: Significant price change for {$productName}",
            PriceAlert::TYPE_OUT_OF_STOCK => "Price Alert: {$productName} out of stock at {$this->scrape->competitor_name}",
        };
    }

    /**
     * Get a detailed description of the alert trigger.
     */
    protected function getAlertDescription(): string
    {
        $productName = $this->alert->product?->getAttributeValue('name') ?? 'A product';
        $scrapePrice = (float) $this->scrape->price;

        return match ($this->alert->alert_type) {
            PriceAlert::TYPE_PRICE_BELOW => "{$productName} price at {$this->scrape->competitor_name} has dropped below R{$this->alert->threshold} to R".number_format($scrapePrice, 2),
            PriceAlert::TYPE_COMPETITOR_BEATS => "{$this->scrape->competitor_name} is now selling {$productName} for R".number_format($scrapePrice, 2).', which is below your price of R'.number_format($this->ourPrice ?? 0, 2),
            PriceAlert::TYPE_PRICE_CHANGE => "{$productName} price at {$this->scrape->competitor_name} has changed significantly",
            PriceAlert::TYPE_OUT_OF_STOCK => "{$productName} has gone out of stock at {$this->scrape->competitor_name}",
        };
    }

    /**
     * Get notification title for in-app display.
     */
    protected function getNotificationTitle(): string
    {
        return match ($this->alert->alert_type) {
            PriceAlert::TYPE_PRICE_BELOW => 'Price Drop Alert',
            PriceAlert::TYPE_COMPETITOR_BEATS => 'Competitor Price Alert',
            PriceAlert::TYPE_PRICE_CHANGE => 'Price Change Alert',
            PriceAlert::TYPE_OUT_OF_STOCK => 'Out of Stock Alert',
        };
    }

    /**
     * Get the icon for this alert type.
     */
    protected function getAlertIcon(): string
    {
        return match ($this->alert->alert_type) {
            PriceAlert::TYPE_PRICE_BELOW => 'heroicon-o-arrow-trending-down',
            PriceAlert::TYPE_COMPETITOR_BEATS => 'heroicon-o-exclamation-triangle',
            PriceAlert::TYPE_PRICE_CHANGE => 'heroicon-o-currency-dollar',
            PriceAlert::TYPE_OUT_OF_STOCK => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get the color for this alert type.
     */
    protected function getAlertColor(): string
    {
        return match ($this->alert->alert_type) {
            PriceAlert::TYPE_PRICE_BELOW => 'success',
            PriceAlert::TYPE_COMPETITOR_BEATS => 'danger',
            PriceAlert::TYPE_PRICE_CHANGE => 'warning',
            PriceAlert::TYPE_OUT_OF_STOCK => 'info',
        };
    }
}
