<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Filament\SupplyPanel\Concerns\HasBrandContext;
use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ProductDeepDive extends Page
{
    use HasBrandContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationLabel = 'Product Deep Dive';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.supply-panel.pages.product-deep-dive';

    #[Url]
    public ?string $sku = null;

    #[Url]
    public int $monthsBack = 12;

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<string, mixed> */
    public array $productInfo = [];

    /** @var array<string, mixed> */
    public array $performanceMetrics = [];

    /** @var array<string, mixed> */
    public array $customerMetrics = [];

    /** @var array<string, mixed> */
    public array $priceMetrics = [];

    /** @var array<int, array{month: string, revenue: float, orders: int, units: int}> */
    public array $trendData = [];

    /** @var array<string, mixed> */
    public array $comparisonData = [];

    /** @var array<string, mixed> */
    public array $chartData = [];

    /** @var array<int, array{sku: string, name: string}> */
    public array $availableProducts = [];

    public function mount(): void
    {
        if (! $this->initializeBrandContext()) {
            $this->error = 'You do not have access to this brand.';
            $this->loading = false;

            return;
        }

        $this->loadAvailableProducts();

        if ($this->sku) {
            $this->loadData();
        } else {
            $this->loading = false;
        }
    }

    protected function onBrandContextChanged(): void
    {
        $this->loadData();
    }

    public function loadAvailableProducts(): void
    {
        if (! $this->brandId) {
            return;
        }

        try {
            $bq = app(BigQueryService::class);
            $brand = Brand::find($this->brandId);

            if (! $brand) {
                return;
            }

            $this->availableProducts = $bq->getProductList($brand->name, 100);
        } catch (\Exception $e) {
            // Silently fail - products list is not critical
            $this->availableProducts = [];
        }
    }

    public function loadData(): void
    {
        if (! $this->brandId || ! $this->sku) {
            $this->loading = false;

            return;
        }

        $this->loading = true;
        $this->error = null;

        try {
            $bq = app(BigQueryService::class);
            $brand = Brand::find($this->brandId);

            if (! $brand) {
                throw new \Exception('Brand not found');
            }

            // Load comprehensive product data from BigQuery
            $data = $bq->getProductDeepDive($brand->name, $this->sku, $this->monthsBack);

            $this->productInfo = $data['product_info'];
            $this->performanceMetrics = $data['performance'];
            $this->customerMetrics = $data['customer'];
            $this->priceMetrics = $data['price'];
            $this->trendData = $data['trend'];
            $this->comparisonData = $data['comparison'];

            // Build chart data
            $this->chartData = $this->buildChartData();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load product data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build chart data for trend visualization.
     *
     * @return array<string, mixed>
     */
    private function buildChartData(): array
    {
        $labels = [];
        $revenueData = [];
        $ordersData = [];
        $unitsData = [];

        foreach ($this->trendData as $row) {
            $labels[] = $row['month'];
            $revenueData[] = $row['revenue'];
            $ordersData[] = $row['orders'];
            $unitsData[] = $row['units'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue (R)',
                    'data' => $revenueData,
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'yAxisID' => 'y',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Orders',
                    'data' => $ordersData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y1',
                    'fill' => false,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Units',
                    'data' => $unitsData,
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'yAxisID' => 'y1',
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    public function updatedBrandId(): void
    {
        $this->sku = null;
        $this->loadAvailableProducts();
        $this->resetData();
    }

    public function updatedSku(): void
    {
        if ($this->sku) {
            $this->loadData();
        } else {
            $this->resetData();
        }
    }

    public function updatedMonthsBack(): void
    {
        if ($this->sku) {
            $this->loadData();
        }
    }

    /**
     * Reset data when product is cleared.
     */
    private function resetData(): void
    {
        $this->productInfo = [];
        $this->performanceMetrics = [];
        $this->customerMetrics = [];
        $this->priceMetrics = [];
        $this->trendData = [];
        $this->comparisonData = [];
        $this->chartData = [];
        $this->loading = false;
    }

    /**
     * Get time period options.
     *
     * @return array<int, string>
     */
    public function getMonthsBackOptions(): array
    {
        return [
            6 => 'Last 6 Months',
            12 => 'Last 12 Months',
            18 => 'Last 18 Months',
            24 => 'Last 24 Months',
        ];
    }

    /**
     * Format currency value.
     */
    public function formatCurrency(float|int $value): string
    {
        if ($value >= 1000000) {
            return 'R'.number_format($value / 1000000, 1).'M';
        }
        if ($value >= 1000) {
            return 'R'.number_format($value / 1000, 1).'K';
        }

        return 'R'.number_format($value, 0);
    }

    /**
     * Format number with K/M suffix.
     */
    public function formatNumber(float|int $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1).'M';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 1).'K';
        }

        return number_format($value, 0);
    }

    /**
     * Format percentage.
     */
    public function formatPercent(float $value): string
    {
        return number_format($value, 1).'%';
    }

    /**
     * Get comparison color class.
     */
    public function getComparisonColorClass(float $value): string
    {
        if ($value > 10) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($value > 0) {
            return 'text-emerald-600 dark:text-emerald-400';
        }
        if ($value >= -10) {
            return 'text-yellow-600 dark:text-yellow-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Get comparison icon.
     */
    public function getComparisonIcon(float $value): string
    {
        if ($value > 0) {
            return '↑';
        }
        if ($value < 0) {
            return '↓';
        }

        return '→';
    }

    /**
     * Get reorder rate color class.
     */
    public function getReorderRateColorClass(float $rate): string
    {
        if ($rate >= 40) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($rate >= 25) {
            return 'text-emerald-600 dark:text-emerald-400';
        }
        if ($rate >= 15) {
            return 'text-yellow-600 dark:text-yellow-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Get promo intensity color class.
     */
    public function getPromoIntensityColorClass(float $rate): string
    {
        if ($rate <= 20) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($rate <= 40) {
            return 'text-yellow-600 dark:text-yellow-400';
        }
        if ($rate <= 60) {
            return 'text-orange-600 dark:text-orange-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Check if product has data.
     */
    public function hasProductData(): bool
    {
        return ! empty($this->productInfo);
    }
}
