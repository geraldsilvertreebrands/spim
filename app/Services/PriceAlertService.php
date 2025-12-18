<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use Illuminate\Support\Collection;

class PriceAlertService
{
    /**
     * Default cooldown period in minutes between alert triggers.
     */
    protected int $defaultCooldownMinutes = 60;

    /**
     * Whether to send notifications when alerts are triggered.
     */
    protected bool $sendNotifications = true;

    /**
     * The notification service instance.
     */
    protected ?PriceAlertNotificationService $notificationService = null;

    /**
     * Process a price scrape and return all triggered alerts.
     *
     * @param  PriceScrape  $scrape  The price scrape to process
     * @param  float|null  $ourPrice  Our price for the product (for competitor_beats alerts)
     * @param  int|null  $cooldownMinutes  Override default cooldown period
     * @return Collection<int, PriceAlert> Alerts that were triggered
     */
    public function processScrape(
        PriceScrape $scrape,
        ?float $ourPrice = null,
        ?int $cooldownMinutes = null
    ): Collection {
        $cooldown = $cooldownMinutes ?? $this->defaultCooldownMinutes;

        $triggeredAlerts = PriceAlert::getTriggeredAlerts($scrape, $ourPrice, $cooldown);

        // Mark each triggered alert and send notifications
        foreach ($triggeredAlerts as $alert) {
            $alert->markTriggered();

            if ($this->sendNotifications) {
                $this->getNotificationService()->sendAlertNotification($alert, $scrape, $ourPrice);
            }
        }

        return $triggeredAlerts;
    }

    /**
     * Get the notification service instance.
     */
    protected function getNotificationService(): PriceAlertNotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = app(PriceAlertNotificationService::class);
        }

        return $this->notificationService;
    }

    /**
     * Set the notification service instance (for testing).
     */
    public function setNotificationService(PriceAlertNotificationService $service): self
    {
        $this->notificationService = $service;

        return $this;
    }

    /**
     * Enable or disable notifications.
     */
    public function withNotifications(bool $enabled = true): self
    {
        $this->sendNotifications = $enabled;

        return $this;
    }

    /**
     * Disable notifications (chainable).
     */
    public function withoutNotifications(): self
    {
        return $this->withNotifications(false);
    }

    /**
     * Check and trigger alerts for a price scrape.
     * Alias for processScrape with a cleaner name for import services.
     *
     * @param  PriceScrape  $scrape  The price scrape to check alerts against
     * @param  float|null  $ourPrice  Our price for competitor comparison (optional)
     * @return Collection<int, PriceAlert> Alerts that were triggered
     */
    public function checkAndTriggerAlerts(
        PriceScrape $scrape,
        ?float $ourPrice = null
    ): Collection {
        return $this->processScrape($scrape, $ourPrice);
    }

    /**
     * Process multiple price scrapes at once.
     *
     * @param  Collection<int, PriceScrape>  $scrapes
     * @param  array<string, float>  $ourPrices  Map of product_id => our_price
     * @return Collection<int, array{alert: PriceAlert, scrape: PriceScrape}>
     */
    public function processMultipleScrapes(
        Collection $scrapes,
        array $ourPrices = [],
        ?int $cooldownMinutes = null
    ): Collection {
        $results = collect();

        foreach ($scrapes as $scrape) {
            $ourPrice = $ourPrices[$scrape->product_id] ?? null;
            $triggered = $this->processScrape($scrape, $ourPrice, $cooldownMinutes);

            foreach ($triggered as $alert) {
                $results->push([
                    'alert' => $alert,
                    'scrape' => $scrape,
                ]);
            }
        }

        return $results;
    }

    /**
     * Create a new price alert for a user.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAlert(User $user, array $data): PriceAlert
    {
        return PriceAlert::create([
            'user_id' => $user->id,
            'product_id' => $data['product_id'] ?? null,
            'competitor_name' => $data['competitor_name'] ?? null,
            'alert_type' => $data['alert_type'],
            'threshold' => $data['threshold'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Create a "price below" alert.
     */
    public function createPriceBelowAlert(
        User $user,
        float $threshold,
        ?string $productId = null,
        ?string $competitorName = null
    ): PriceAlert {
        return $this->createAlert($user, [
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => $threshold,
            'product_id' => $productId,
            'competitor_name' => $competitorName,
        ]);
    }

    /**
     * Create a "competitor beats" alert.
     */
    public function createCompetitorBeatsAlert(
        User $user,
        ?string $productId = null,
        ?string $competitorName = null
    ): PriceAlert {
        return $this->createAlert($user, [
            'alert_type' => PriceAlert::TYPE_COMPETITOR_BEATS,
            'product_id' => $productId,
            'competitor_name' => $competitorName,
        ]);
    }

    /**
     * Create a "price change" alert with percentage threshold.
     */
    public function createPriceChangeAlert(
        User $user,
        float $percentThreshold,
        ?string $productId = null,
        ?string $competitorName = null
    ): PriceAlert {
        return $this->createAlert($user, [
            'alert_type' => PriceAlert::TYPE_PRICE_CHANGE,
            'threshold' => $percentThreshold,
            'product_id' => $productId,
            'competitor_name' => $competitorName,
        ]);
    }

    /**
     * Create an "out of stock" alert.
     */
    public function createOutOfStockAlert(
        User $user,
        ?string $productId = null,
        ?string $competitorName = null
    ): PriceAlert {
        return $this->createAlert($user, [
            'alert_type' => PriceAlert::TYPE_OUT_OF_STOCK,
            'product_id' => $productId,
            'competitor_name' => $competitorName,
        ]);
    }

    /**
     * Get all alerts for a user.
     *
     * @return Collection<int, PriceAlert>
     */
    public function getUserAlerts(User $user, bool $activeOnly = false): Collection
    {
        $query = PriceAlert::forUser($user);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get all alerts for a product.
     *
     * @return Collection<int, PriceAlert>
     */
    public function getProductAlerts(string|Entity $product, bool $activeOnly = false): Collection
    {
        $query = PriceAlert::forProduct($product);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get summary statistics for a user's alerts.
     *
     * @return array<string, int|array<string, int>>
     */
    public function getAlertStats(User $user): array
    {
        $alerts = PriceAlert::forUser($user)->get();

        return [
            'total' => $alerts->count(),
            'active' => $alerts->where('is_active', true)->count(),
            'inactive' => $alerts->where('is_active', false)->count(),
            'triggered_today' => $alerts->filter(
                fn (PriceAlert $a): bool => (bool) $a->last_triggered_at?->isToday()
            )->count(),
            'by_type' => $alerts->groupBy('alert_type')
                ->map(fn ($group) => $group->count())
                ->toArray(),
        ];
    }

    /**
     * Bulk activate alerts.
     *
     * @param  array<int>  $alertIds
     */
    public function activateAlerts(array $alertIds): int
    {
        return PriceAlert::whereIn('id', $alertIds)
            ->update(['is_active' => true]);
    }

    /**
     * Bulk deactivate alerts.
     *
     * @param  array<int>  $alertIds
     */
    public function deactivateAlerts(array $alertIds): int
    {
        return PriceAlert::whereIn('id', $alertIds)
            ->update(['is_active' => false]);
    }

    /**
     * Delete alerts for a user.
     *
     * @param  array<int>|null  $alertIds  If null, deletes all user's alerts
     */
    public function deleteAlerts(User $user, ?array $alertIds = null): int
    {
        $query = PriceAlert::forUser($user);

        if ($alertIds !== null) {
            $query->whereIn('id', $alertIds);
        }

        return $query->delete();
    }

    /**
     * Set the default cooldown period.
     */
    public function setCooldownMinutes(int $minutes): self
    {
        $this->defaultCooldownMinutes = $minutes;

        return $this;
    }

    /**
     * Get the default cooldown period.
     */
    public function getCooldownMinutes(): int
    {
        return $this->defaultCooldownMinutes;
    }
}
