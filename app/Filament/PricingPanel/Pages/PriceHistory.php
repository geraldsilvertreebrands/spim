<?php

namespace App\Filament\PricingPanel\Pages;

use App\Models\Entity;
use App\Models\PriceScrape;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class PriceHistory extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Price History';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pricing-panel.pages.price-history';

    public ?string $selectedProductId = null;

    public string $dateRange = '30';

    /** @var array<string, mixed> */
    public array $chartData = [];

    /** @var array<int, array{id: string, name: string}> */
    public array $products = [];

    /** @var array<int, string> */
    public array $competitors = [];

    public ?float $ourPrice = null;

    public bool $loading = false;

    public ?string $error = null;

    public function mount(): void
    {
        $this->loadProducts();
    }

    /**
     * Load products that have price scrape data.
     */
    public function loadProducts(): void
    {
        $productIds = PriceScrape::select('product_id')
            ->distinct()
            ->pluck('product_id');

        $this->products = Entity::whereIn('id', $productIds)
            ->get()
            ->map(function (Entity $entity) {
                $name = $entity->name ?? $entity->getAttr('name') ?? 'Unknown Product';

                return [
                    'id' => $entity->id,
                    'name' => $name,
                ];
            })
            ->sortBy('name')
            ->values()
            ->toArray();

        // Auto-select first product if available
        if (! empty($this->products) && $this->selectedProductId === null) {
            $this->selectedProductId = $this->products[0]['id'];
            $this->loadChartData();
        }
    }

    /**
     * Load chart data when product selection changes.
     */
    public function updatedSelectedProductId(): void
    {
        $this->loadChartData();
    }

    /**
     * Load chart data when date range changes.
     */
    public function updatedDateRange(): void
    {
        $this->loadChartData();
    }

    /**
     * Load price history chart data for the selected product.
     */
    public function loadChartData(): void
    {
        if ($this->selectedProductId === null) {
            $this->chartData = [];
            $this->competitors = [];
            $this->ourPrice = null;

            return;
        }

        $this->loading = true;
        $this->error = null;

        try {
            $days = (int) $this->dateRange;
            $startDate = now()->subDays($days);
            $endDate = now();

            // Get product entity
            $product = Entity::find($this->selectedProductId);
            if (! $product) {
                $this->error = 'Product not found';
                $this->loading = false;

                return;
            }

            // Get our price for reference line
            $this->ourPrice = $this->getProductPrice($product);

            // Get all competitors for this product
            $this->competitors = PriceScrape::forProduct($this->selectedProductId)
                ->select('competitor_name')
                ->distinct()
                ->orderBy('competitor_name')
                ->pluck('competitor_name')
                ->toArray();

            // Build chart data
            $this->chartData = $this->buildChartData($startDate, $endDate);

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load price history: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Get the price for a product entity.
     */
    protected function getProductPrice(Entity $product): ?float
    {
        $priceAttrs = ['price', 'selling_price', 'retail_price', 'base_price'];

        foreach ($priceAttrs as $attr) {
            $price = $product->getAttr($attr);
            if ($price !== null) {
                return (float) $price;
            }
        }

        return null;
    }

    /**
     * Build the chart data structure for Chart.js.
     *
     * @return array<string, mixed>
     */
    protected function buildChartData(Carbon $startDate, Carbon $endDate): array
    {
        // Generate date labels
        $labels = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $labels[] = $currentDate->format('M d');
            $currentDate->addDay();
        }

        // Build datasets for each competitor
        $datasets = [];
        $colorIndex = 0;
        $colors = $this->getChartColors();

        foreach ($this->competitors as $competitor) {
            $priceHistory = $this->getPriceHistoryForCompetitor(
                $this->selectedProductId,
                $competitor,
                $startDate,
                $endDate
            );

            $color = $colors[$colorIndex % count($colors)];

            $datasets[] = [
                'label' => $competitor,
                'data' => $priceHistory,
                'borderColor' => $color['border'],
                'backgroundColor' => $color['background'],
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.1,
                'pointRadius' => 3,
                'pointHoverRadius' => 5,
            ];

            $colorIndex++;
        }

        // Add our price as a reference line if available
        if ($this->ourPrice !== null) {
            $ourPriceData = array_fill(0, count($labels), $this->ourPrice);
            $datasets[] = [
                'label' => 'Our Price',
                'data' => $ourPriceData,
                'borderColor' => 'rgb(99, 102, 241)',
                'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                'borderWidth' => 3,
                'borderDash' => [5, 5],
                'fill' => false,
                'pointRadius' => 0,
                'pointHoverRadius' => 0,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Get price history data points for a specific competitor.
     *
     * @return array<int, float|null>
     */
    protected function getPriceHistoryForCompetitor(
        string $productId,
        string $competitor,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        // Get all scrapes for this product/competitor in the date range
        $scrapes = PriceScrape::forProduct($productId)
            ->forCompetitor($competitor)
            ->dateRange($startDate, $endDate)
            ->orderBy('scraped_at', 'asc')
            ->get(['price', 'scraped_at']);

        // Create a map of date -> price
        $priceMap = [];
        foreach ($scrapes as $scrape) {
            $dateKey = $scrape->scraped_at->format('M d');
            $priceMap[$dateKey] = (float) $scrape->price;
        }

        // Generate data points for each day
        $dataPoints = [];
        $currentDate = $startDate->copy();
        $lastPrice = null;

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('M d');

            if (isset($priceMap[$dateKey])) {
                $lastPrice = $priceMap[$dateKey];
            }

            // Use last known price or null if no data yet
            $dataPoints[] = $lastPrice;
            $currentDate->addDay();
        }

        return $dataPoints;
    }

    /**
     * Get chart colors for competitor lines.
     *
     * @return array<int, array{border: string, background: string}>
     */
    protected function getChartColors(): array
    {
        return [
            ['border' => 'rgb(239, 68, 68)', 'background' => 'rgba(239, 68, 68, 0.1)'],    // Red
            ['border' => 'rgb(34, 197, 94)', 'background' => 'rgba(34, 197, 94, 0.1)'],    // Green
            ['border' => 'rgb(251, 191, 36)', 'background' => 'rgba(251, 191, 36, 0.1)'],  // Amber
            ['border' => 'rgb(168, 85, 247)', 'background' => 'rgba(168, 85, 247, 0.1)'],  // Purple
            ['border' => 'rgb(14, 165, 233)', 'background' => 'rgba(14, 165, 233, 0.1)'],  // Sky blue
            ['border' => 'rgb(236, 72, 153)', 'background' => 'rgba(236, 72, 153, 0.1)'],  // Pink
            ['border' => 'rgb(245, 158, 11)', 'background' => 'rgba(245, 158, 11, 0.1)'],  // Orange
            ['border' => 'rgb(20, 184, 166)', 'background' => 'rgba(20, 184, 166, 0.1)'],  // Teal
        ];
    }

    /**
     * Refresh the chart data.
     */
    public function refresh(): void
    {
        $this->loadChartData();
    }

    /**
     * Get the selected product name.
     */
    public function getSelectedProductName(): ?string
    {
        if ($this->selectedProductId === null) {
            return null;
        }

        foreach ($this->products as $product) {
            if ($product['id'] === $this->selectedProductId) {
                return $product['name'];
            }
        }

        return null;
    }

    /**
     * Format price for display.
     */
    public function formatPrice(?float $price): string
    {
        if ($price === null) {
            return '-';
        }

        return 'R'.number_format($price, 2);
    }

    /**
     * Get date range options for dropdown.
     *
     * @return array<int|string, string>
     */
    public function getDateRangeOptions(): array
    {
        return [
            '7' => 'Last 7 days',
            '14' => 'Last 14 days',
            '30' => 'Last 30 days',
            '60' => 'Last 60 days',
            '90' => 'Last 90 days',
        ];
    }
}
