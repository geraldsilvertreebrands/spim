<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PriceAlert extends Model
{
    use HasFactory;

    // Alert Types
    public const TYPE_PRICE_BELOW = 'price_below';

    public const TYPE_COMPETITOR_BEATS = 'competitor_beats';

    public const TYPE_PRICE_CHANGE = 'price_change';

    public const TYPE_OUT_OF_STOCK = 'out_of_stock';

    public const ALERT_TYPES = [
        self::TYPE_PRICE_BELOW,
        self::TYPE_COMPETITOR_BEATS,
        self::TYPE_PRICE_CHANGE,
        self::TYPE_OUT_OF_STOCK,
    ];

    protected $fillable = [
        'user_id',
        'product_id',
        'competitor_name',
        'alert_type',
        'threshold',
        'is_active',
        'last_triggered_at',
    ];

    protected $casts = [
        'threshold' => 'decimal:2',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    // =====================
    // Relationships
    // =====================

    /**
     * Get the user who owns this alert.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product (entity) this alert is for.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'product_id');
    }

    // =====================
    // Scopes
    // =====================

    /**
     * Scope to filter active alerts only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter inactive alerts only.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to filter alerts by user.
     */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter alerts by product.
     */
    public function scopeForProduct(Builder $query, string|Entity $product): Builder
    {
        $productId = $product instanceof Entity ? $product->id : $product;

        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter alerts by competitor.
     */
    public function scopeForCompetitor(Builder $query, string $competitorName): Builder
    {
        return $query->where('competitor_name', $competitorName);
    }

    /**
     * Scope to filter alerts by type.
     */
    public function scopeByType(Builder $query, string $alertType): Builder
    {
        return $query->where('alert_type', $alertType);
    }

    /**
     * Scope to get alerts that haven't been triggered recently.
     */
    public function scopeNotTriggeredSince(Builder $query, Carbon $since): Builder
    {
        return $query->where(function ($q) use ($since) {
            $q->whereNull('last_triggered_at')
                ->orWhere('last_triggered_at', '<', $since);
        });
    }

    /**
     * Scope to get alerts matching a price scrape (by product and/or competitor).
     */
    public function scopeMatchingScrape(Builder $query, PriceScrape $scrape): Builder
    {
        return $query->where(function ($q) use ($scrape) {
            // Match by product (if set) or global alerts (no product set)
            $q->where(function ($productQuery) use ($scrape) {
                $productQuery->where('product_id', $scrape->product_id)
                    ->orWhereNull('product_id');
            });

            // Match by competitor (if set) or all competitors (no competitor set)
            $q->where(function ($competitorQuery) use ($scrape) {
                $competitorQuery->where('competitor_name', $scrape->competitor_name)
                    ->orWhereNull('competitor_name');
            });
        });
    }

    // =====================
    // Alert Checking Logic
    // =====================

    /**
     * Check if this alert should be triggered based on a price scrape.
     *
     * @param  PriceScrape  $scrape  The price scrape to check against
     * @param  float|null  $ourPrice  Our price (required for competitor_beats type)
     */
    public function shouldTrigger(PriceScrape $scrape, ?float $ourPrice = null): bool
    {
        // Inactive alerts never trigger
        if (! $this->is_active) {
            return false;
        }

        // Check if alert matches this scrape
        if (! $this->matchesScrape($scrape)) {
            return false;
        }

        return match ($this->alert_type) {
            self::TYPE_PRICE_BELOW => $this->checkPriceBelow($scrape),
            self::TYPE_COMPETITOR_BEATS => $this->checkCompetitorBeats($scrape, $ourPrice),
            self::TYPE_PRICE_CHANGE => $this->checkPriceChange($scrape),
            self::TYPE_OUT_OF_STOCK => $this->checkOutOfStock($scrape),
            default => false,
        };
    }

    /**
     * Check if the alert matches the given price scrape.
     */
    public function matchesScrape(PriceScrape $scrape): bool
    {
        // If product is specified, it must match (use string comparison for ULID)
        if ($this->product_id !== null && (string) $this->product_id !== (string) $scrape->product_id) {
            return false;
        }

        // If competitor is specified, it must match
        if ($this->competitor_name !== null && $this->competitor_name !== $scrape->competitor_name) {
            return false;
        }

        return true;
    }

    /**
     * Check if price dropped below threshold.
     */
    protected function checkPriceBelow(PriceScrape $scrape): bool
    {
        if ($this->threshold === null) {
            return false;
        }

        return (float) $scrape->price < (float) $this->threshold;
    }

    /**
     * Check if competitor price beats our price.
     */
    protected function checkCompetitorBeats(PriceScrape $scrape, ?float $ourPrice): bool
    {
        if ($ourPrice === null) {
            return false;
        }

        // Competitor beats us if their price is lower
        return (float) $scrape->price < $ourPrice;
    }

    /**
     * Check if price changed by more than threshold percentage.
     */
    protected function checkPriceChange(PriceScrape $scrape): bool
    {
        if ($this->threshold === null) {
            return false;
        }

        $changePercent = $scrape->getPriceChangePercent();

        if ($changePercent === null) {
            return false;
        }

        // Trigger if absolute change exceeds threshold
        return abs($changePercent) >= (float) $this->threshold;
    }

    /**
     * Check if product went out of stock.
     */
    protected function checkOutOfStock(PriceScrape $scrape): bool
    {
        // Only trigger if currently out of stock AND status changed
        return ! $scrape->in_stock && $scrape->hasStockStatusChanged();
    }

    // =====================
    // Alert Actions
    // =====================

    /**
     * Mark this alert as triggered.
     */
    public function markTriggered(): self
    {
        $this->update(['last_triggered_at' => now()]);

        return $this;
    }

    /**
     * Activate this alert.
     */
    public function activate(): self
    {
        $this->update(['is_active' => true]);

        return $this;
    }

    /**
     * Deactivate this alert.
     */
    public function deactivate(): self
    {
        $this->update(['is_active' => false]);

        return $this;
    }

    /**
     * Check if the alert was triggered recently (within cooldown period).
     */
    public function wasTriggeredRecently(int $cooldownMinutes = 60): bool
    {
        if ($this->last_triggered_at === null) {
            return false;
        }

        return $this->last_triggered_at->isAfter(now()->subMinutes($cooldownMinutes));
    }

    // =====================
    // Static Methods
    // =====================

    /**
     * Get all active alerts that should trigger for a given price scrape.
     *
     * @param  PriceScrape  $scrape  The price scrape to check
     * @param  float|null  $ourPrice  Our price for the product (for competitor_beats alerts)
     * @param  int  $cooldownMinutes  Minimum minutes between triggers for same alert
     * @return Collection<int, static>
     */
    public static function getTriggeredAlerts(
        PriceScrape $scrape,
        ?float $ourPrice = null,
        int $cooldownMinutes = 60
    ): Collection {
        return static::query()
            ->active()
            ->matchingScrape($scrape)
            ->notTriggeredSince(now()->subMinutes($cooldownMinutes))
            ->get()
            ->filter(fn (self $alert) => $alert->shouldTrigger($scrape, $ourPrice));
    }

    /**
     * Get all alert types with their labels.
     *
     * @return array<string, string>
     */
    public static function getAlertTypeLabels(): array
    {
        return [
            self::TYPE_PRICE_BELOW => 'Price drops below threshold',
            self::TYPE_COMPETITOR_BEATS => 'Competitor beats our price',
            self::TYPE_PRICE_CHANGE => 'Price changes by percentage',
            self::TYPE_OUT_OF_STOCK => 'Product goes out of stock',
        ];
    }

    /**
     * Get human-readable label for this alert's type.
     */
    public function getAlertTypeLabel(): string
    {
        return self::getAlertTypeLabels()[$this->alert_type] ?? $this->alert_type;
    }

    /**
     * Get a human-readable description of this alert.
     */
    public function getDescription(): string
    {
        $parts = [];

        switch ($this->alert_type) {
            case self::TYPE_PRICE_BELOW:
                $parts[] = "Price drops below R{$this->threshold}";
                break;
            case self::TYPE_COMPETITOR_BEATS:
                $parts[] = 'Competitor beats our price';
                break;
            case self::TYPE_PRICE_CHANGE:
                $parts[] = "Price changes by more than {$this->threshold}%";
                break;
            case self::TYPE_OUT_OF_STOCK:
                $parts[] = 'Product goes out of stock';
                break;
        }

        if ($this->competitor_name) {
            $parts[] = "at {$this->competitor_name}";
        }

        return implode(' ', $parts);
    }
}
