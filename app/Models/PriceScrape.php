<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PriceScrape extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'competitor_name',
        'competitor_url',
        'competitor_sku',
        'price',
        'currency',
        'in_stock',
        'scraped_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'in_stock' => 'boolean',
        'scraped_at' => 'datetime',
    ];

    // =====================
    // Relationships
    // =====================

    /**
     * Get the product (entity) this price scrape belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'product_id');
    }

    // =====================
    // Date Range Scopes
    // =====================

    /**
     * Scope to filter scrapes within a date range.
     */
    public function scopeDateRange(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        $startDate = $start instanceof Carbon ? $start : Carbon::parse($start);
        $endDate = $end instanceof Carbon ? $end : Carbon::parse($end);

        return $query->whereBetween('scraped_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter scrapes from the last N days.
     */
    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('scraped_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter scrapes from last week.
     */
    public function scopeLastWeek(Builder $query): Builder
    {
        return $query->where('scraped_at', '>=', now()->subDays(7));
    }

    /**
     * Scope to filter scrapes from last month.
     */
    public function scopeLastMonth(Builder $query): Builder
    {
        return $query->where('scraped_at', '>=', now()->subDays(30));
    }

    /**
     * Scope to filter scrapes from today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('scraped_at', today());
    }

    // =====================
    // Competitor Scopes
    // =====================

    /**
     * Scope to filter by competitor name.
     */
    public function scopeForCompetitor(Builder $query, string $competitorName): Builder
    {
        return $query->where('competitor_name', $competitorName);
    }

    /**
     * Scope to filter by product ID.
     */
    public function scopeForProduct(Builder $query, string $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by in-stock status.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('in_stock', true);
    }

    /**
     * Scope to filter out-of-stock items.
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('in_stock', false);
    }

    /**
     * Scope to order scrapes by most recent first (by scraped_at).
     */
    public function scopeMostRecent(Builder $query): Builder
    {
        return $query->orderBy('scraped_at', 'desc');
    }

    // =====================
    // Price Change Detection
    // =====================

    /**
     * Get the previous price scrape for the same product and competitor.
     */
    public function getPreviousScrape(): ?self
    {
        return static::query()
            ->where('product_id', $this->product_id)
            ->where('competitor_name', $this->competitor_name)
            ->where('scraped_at', '<', $this->scraped_at)
            ->orderBy('scraped_at', 'desc')
            ->first();
    }

    /**
     * Calculate the price change from the previous scrape.
     *
     * @return float|null Price change amount (positive = increase, negative = decrease)
     */
    public function getPriceChange(): ?float
    {
        $previous = $this->getPreviousScrape();

        if (! $previous) {
            return null;
        }

        return round((float) $this->price - (float) $previous->price, 2);
    }

    /**
     * Calculate the price change percentage from the previous scrape.
     *
     * @return float|null Percentage change (positive = increase, negative = decrease)
     */
    public function getPriceChangePercent(): ?float
    {
        $previous = $this->getPreviousScrape();

        if (! $previous || (float) $previous->price === 0.0) {
            return null;
        }

        $change = (float) $this->price - (float) $previous->price;

        return round(($change / (float) $previous->price) * 100, 2);
    }

    /**
     * Check if the price increased from the previous scrape.
     */
    public function hasPriceIncreased(): bool
    {
        $change = $this->getPriceChange();

        return $change !== null && $change > 0;
    }

    /**
     * Check if the price decreased from the previous scrape.
     */
    public function hasPriceDecreased(): bool
    {
        $change = $this->getPriceChange();

        return $change !== null && $change < 0;
    }

    /**
     * Check if the price changed by more than a given percentage.
     */
    public function hasPriceChangedByPercent(float $threshold): bool
    {
        $changePercent = $this->getPriceChangePercent();

        return $changePercent !== null && abs($changePercent) >= $threshold;
    }

    /**
     * Check if the stock status changed from the previous scrape.
     */
    public function hasStockStatusChanged(): bool
    {
        $previous = $this->getPreviousScrape();

        if (! $previous) {
            return false;
        }

        return $this->in_stock !== $previous->in_stock;
    }

    // =====================
    // Aggregation Methods
    // =====================

    /**
     * Get the price history for a product from a specific competitor.
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function getPriceHistory(
        string $productId,
        string $competitorName,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $query = static::query()
            ->forProduct($productId)
            ->forCompetitor($competitorName)
            ->orderBy('scraped_at', 'asc');

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query->get(['price', 'scraped_at', 'in_stock', 'currency']);
    }

    /**
     * Get all competitor prices for a product (most recent only).
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function getLatestCompetitorPrices(string $productId): Collection
    {
        // Get distinct competitor names for this product
        $competitors = static::query()
            ->forProduct($productId)
            ->select('competitor_name')
            ->distinct()
            ->pluck('competitor_name');

        // For each competitor, get the most recent scrape
        return $competitors->map(function (string $competitorName) use ($productId) {
            return static::query()
                ->forProduct($productId)
                ->forCompetitor($competitorName)
                ->orderBy('scraped_at', 'desc')
                ->first();
        })->filter();
    }

    /**
     * Get all distinct competitor names.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function getCompetitors(): Collection
    {
        return static::query()
            ->select('competitor_name')
            ->distinct()
            ->orderBy('competitor_name')
            ->pluck('competitor_name');
    }

    /**
     * Get the average price for a product across all competitors.
     */
    public static function getAveragePrice(string $productId): ?float
    {
        $latestPrices = static::getLatestCompetitorPrices($productId);

        if ($latestPrices->isEmpty()) {
            return null;
        }

        return round($latestPrices->avg('price'), 2);
    }

    /**
     * Get the lowest competitor price for a product.
     */
    public static function getLowestPrice(string $productId): ?self
    {
        $latestPrices = static::getLatestCompetitorPrices($productId);

        if ($latestPrices->isEmpty()) {
            return null;
        }

        return $latestPrices->sortBy('price')->first();
    }

    /**
     * Get the highest competitor price for a product.
     */
    public static function getHighestPrice(string $productId): ?self
    {
        $latestPrices = static::getLatestCompetitorPrices($productId);

        if ($latestPrices->isEmpty()) {
            return null;
        }

        return $latestPrices->sortByDesc('price')->first();
    }

    // =====================
    // Utility Methods
    // =====================

    /**
     * Format the price with currency symbol.
     */
    public function getFormattedPrice(): string
    {
        $symbol = match ($this->currency) {
            'ZAR' => 'R',
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'GBP' => "\u{00A3}",
            default => $this->currency.' ',
        };

        return $symbol.number_format((float) $this->price, 2);
    }

    /**
     * Get a human-readable time since the scrape.
     */
    public function getTimeSinceScrape(): string
    {
        return $this->scraped_at->diffForHumans();
    }
}
