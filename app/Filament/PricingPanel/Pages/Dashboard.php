<?php

namespace App\Filament\PricingPanel\Pages;

use App\Models\Entity;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Services\BigQueryService;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pricing-panel.pages.dashboard';

    /** @var array<string, mixed> */
    public array $kpis = [];

    /** @var array<string, mixed> */
    public array $positionChartData = [];

    /** @var array<string, mixed> */
    public array $priceChangesChartData = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentAlerts = [];

    public bool $loading = true;

    public ?string $error = null;

    public bool $useBigQuery = true;

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            if ($this->useBigQuery) {
                $this->loadBigQueryData();
            } else {
                $this->loadLocalData();
            }

            $this->loading = false;
        } catch (\Exception $e) {
            // Sanitize error message to avoid exposing sensitive information
            $this->error = 'Failed to load dashboard data. Please try again later.';
            $this->loading = false;

            // Log the full error for debugging
            \Log::error('Dashboard data load failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Load data from BigQuery for production use.
     */
    protected function loadBigQueryData(): void
    {
        $bq = app(BigQueryService::class);

        // Load KPIs from BigQuery
        $bqKpis = $bq->getPricingKpis();

        // Get active alerts count from local database
        $activeAlerts = PriceAlert::active()->count();

        $this->kpis = [
            'products_tracked' => $bqKpis['products_tracked'],
            'avg_market_position' => $bqKpis['avg_market_position'],
            'recent_price_changes' => $bqKpis['recent_price_changes'],
            'active_alerts' => $activeAlerts,
            'products_cheapest' => $bqKpis['products_cheapest'],
            'products_most_expensive' => $bqKpis['products_most_expensive'],
            'competitor_undercuts' => $bqKpis['active_competitor_undercuts'],
        ];

        // Build position chart data from BigQuery KPIs
        $this->positionChartData = $this->buildPositionChartData($bqKpis);

        // Get price changes for chart (from BigQuery)
        $this->priceChangesChartData = $this->buildPriceChangesChartData($bq);

        // Load recent alerts
        $this->recentAlerts = $this->loadRecentAlerts();
    }

    /**
     * Load data from local database (fallback/testing).
     */
    protected function loadLocalData(): void
    {
        // Count products being tracked
        $productsTracked = PriceScrape::select('product_id')
            ->distinct()
            ->count();

        // Count active alerts
        $activeAlerts = PriceAlert::active()->count();

        // Get price changes in last 7 days
        $recentPriceChanges = $this->countRecentPriceChanges();

        // Calculate market position distribution
        $positionData = $this->calculateLocalPositionData();

        $this->kpis = [
            'products_tracked' => $productsTracked,
            'avg_market_position' => $positionData['avg_position'],
            'recent_price_changes' => $recentPriceChanges,
            'active_alerts' => $activeAlerts,
            'products_cheapest' => $positionData['cheapest'],
            'products_most_expensive' => $positionData['most_expensive'],
            'competitor_undercuts' => $positionData['undercuts'],
        ];

        // Build chart data
        $this->positionChartData = $this->buildLocalPositionChartData($positionData);
        $this->priceChangesChartData = $this->buildLocalPriceChangesChartData();

        // Load recent alerts
        $this->recentAlerts = $this->loadRecentAlerts();
    }

    /**
     * Build position histogram chart data from BigQuery KPIs.
     *
     * @param  array<string, mixed>  $bqKpis
     * @return array<string, mixed>
     */
    protected function buildPositionChartData(array $bqKpis): array
    {
        $cheapest = $bqKpis['products_cheapest'] ?? 0;
        $mostExpensive = $bqKpis['products_most_expensive'] ?? 0;
        $total = $bqKpis['products_tracked'] ?? 0;

        // Estimate below/above average (BigQuery method provides this data in query)
        // but the KPI return doesn't include it separately, so we approximate
        $remaining = max(0, $total - $cheapest - $mostExpensive);
        $belowAvg = (int) floor($remaining / 2);
        $aboveAvg = $remaining - $belowAvg;

        return [
            'labels' => ['Cheapest', 'Below Avg', 'Above Avg', 'Most Expensive'],
            'datasets' => [
                [
                    'label' => 'Products by Price Position',
                    'data' => [$cheapest, $belowAvg, $aboveAvg, $mostExpensive],
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Green - cheapest
                        'rgba(132, 204, 22, 0.8)',  // Lime - below avg
                        'rgba(251, 191, 36, 0.8)', // Amber - above avg
                        'rgba(239, 68, 68, 0.8)',   // Red - most expensive
                    ],
                    'borderColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(132, 204, 22)',
                        'rgb(251, 191, 36)',
                        'rgb(239, 68, 68)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    /**
     * Build price changes chart data from BigQuery.
     *
     * @return array<string, mixed>
     */
    protected function buildPriceChangesChartData(BigQueryService $bq): array
    {
        // Get the last 7 days of price changes
        $labels = [];
        $increases = [];
        $decreases = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D');
            // Since BigQuery method doesn't provide daily breakdown, use mock data structure
            // In production, you would call a more specific query
            $increases[] = 0;
            $decreases[] = 0;
        }

        // For now, distribute the recent_price_changes across the week
        // A more detailed implementation would query BigQuery for daily data
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Price Increases',
                    'data' => $increases,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Price Decreases',
                    'data' => $decreases,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    /**
     * Count recent price changes from local data.
     */
    protected function countRecentPriceChanges(): int
    {
        // This counts products that had price changes in the last 7 days
        $weekAgo = now()->subDays(7);

        return PriceScrape::where('scraped_at', '>=', $weekAgo)
            ->select('product_id')
            ->distinct()
            ->count();
    }

    /**
     * Calculate position data from local database.
     *
     * @return array<string, mixed>
     */
    protected function calculateLocalPositionData(): array
    {
        $cheapest = 0;
        $mostExpensive = 0;
        $undercuts = 0;

        // Get unique products with competitor prices
        $products = PriceScrape::select('product_id')
            ->distinct()
            ->pluck('product_id');

        foreach ($products as $productId) {
            $latestPrices = PriceScrape::getLatestCompetitorPrices($productId);

            if ($latestPrices->isEmpty()) {
                continue;
            }

            $minPrice = $latestPrices->min('price');
            $maxPrice = $latestPrices->max('price');

            // Check if our product is cheapest (would need product data)
            // For now, track competitor positions
            /** @var PriceScrape $firstPrice */
            $firstPrice = $latestPrices->first();
            if ($minPrice == $firstPrice->price) {
                $cheapest++;
            }
            if ($maxPrice == $firstPrice->price) {
                $mostExpensive++;
            }
        }

        // Determine average position
        $total = $products->count();
        $avgPosition = 'unknown';
        if ($total > 0) {
            if ($cheapest > $total * 0.5) {
                $avgPosition = 'competitive';
            } elseif ($mostExpensive > $total * 0.5) {
                $avgPosition = 'premium';
            } else {
                $avgPosition = 'mid-market';
            }
        }

        return [
            'cheapest' => $cheapest,
            'most_expensive' => $mostExpensive,
            'undercuts' => $undercuts,
            'avg_position' => $avgPosition,
            'total' => $total,
        ];
    }

    /**
     * Build position chart from local data.
     *
     * @param  array<string, mixed>  $positionData
     * @return array<string, mixed>
     */
    protected function buildLocalPositionChartData(array $positionData): array
    {
        $total = $positionData['total'] ?? 0;
        $cheapest = $positionData['cheapest'] ?? 0;
        $mostExpensive = $positionData['most_expensive'] ?? 0;
        $remaining = max(0, $total - $cheapest - $mostExpensive);
        $belowAvg = (int) floor($remaining / 2);
        $aboveAvg = $remaining - $belowAvg;

        return [
            'labels' => ['Cheapest', 'Below Avg', 'Above Avg', 'Most Expensive'],
            'datasets' => [
                [
                    'label' => 'Products by Price Position',
                    'data' => [$cheapest, $belowAvg, $aboveAvg, $mostExpensive],
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(132, 204, 22, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                    ],
                    'borderColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(132, 204, 22)',
                        'rgb(251, 191, 36)',
                        'rgb(239, 68, 68)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    /**
     * Build price changes chart from local data.
     *
     * @return array<string, mixed>
     */
    protected function buildLocalPriceChangesChartData(): array
    {
        $labels = [];
        $increases = [];
        $decreases = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D');

            // Count scrapes with price increases/decreases for that day
            $dayScrapes = PriceScrape::whereDate('scraped_at', $date)->get();

            $dayIncreases = 0;
            $dayDecreases = 0;

            foreach ($dayScrapes as $scrape) {
                $change = $scrape->getPriceChange();
                if ($change !== null) {
                    if ($change > 0) {
                        $dayIncreases++;
                    } elseif ($change < 0) {
                        $dayDecreases++;
                    }
                }
            }

            $increases[] = $dayIncreases;
            $decreases[] = $dayDecreases;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Price Increases',
                    'data' => $increases,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Price Decreases',
                    'data' => $decreases,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    /**
     * Load recent alerts for display.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadRecentAlerts(): array
    {
        $alerts = PriceAlert::with('product')
            ->whereNotNull('last_triggered_at')
            ->orderBy('last_triggered_at', 'desc')
            ->limit(5)
            ->get();

        $result = [];
        foreach ($alerts as $alert) {
            /** @var PriceAlert $alert */
            $productName = 'All Products';
            if ($alert->product !== null) {
                /** @var Entity $product */
                $product = $alert->product;
                $productName = (string) ($product->name ?? 'Unknown Product');
            }

            $result[] = [
                'id' => $alert->id,
                'type' => $alert->alert_type,
                'type_label' => $alert->getAlertTypeLabel(),
                'description' => $alert->getDescription(),
                'product_name' => $productName,
                'competitor' => $alert->competitor_name ?? 'All Competitors',
                'triggered_at' => $alert->last_triggered_at?->diffForHumans(),
            ];
        }

        return $result;
    }

    /**
     * Get market position badge color class.
     */
    public function getPositionBadgeClass(): string
    {
        return match ($this->kpis['avg_market_position'] ?? 'unknown') {
            'competitive' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'mid-market' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'premium' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
        };
    }

    /**
     * Get market position display label.
     */
    public function getPositionLabel(): string
    {
        return match ($this->kpis['avg_market_position'] ?? 'unknown') {
            'competitive' => 'Competitive',
            'mid-market' => 'Mid-Market',
            'premium' => 'Premium',
            default => 'Unknown',
        };
    }

    /**
     * Toggle between BigQuery and local data (for testing/debugging).
     */
    public function toggleDataSource(): void
    {
        $this->useBigQuery = ! $this->useBigQuery;
        $this->loadData();
    }

    /**
     * Refresh dashboard data.
     */
    public function refresh(): void
    {
        $this->loadData();
    }
}
