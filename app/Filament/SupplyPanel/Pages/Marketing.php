<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Filament\SupplyPanel\Concerns\HasBrandContext;
use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Marketing extends Page
{
    use HasBrandContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Marketing';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.supply-panel.pages.marketing';

    #[Url]
    public int $monthsBack = 12;

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<string, mixed> */
    public array $summaryStats = [];

    /** @var array<int, array<string, mixed>> */
    public array $campaigns = [];

    /** @var array<int, array{coupon_code: string, description: string, orders: int, revenue: float, units: int, discount_given: float, avg_discount_pct: float, first_used: string, last_used: string}> */
    public array $promoCampaigns = [];

    /** @var array<string, mixed> */
    public array $discountAnalysis = [];

    /** @var array<int, array{month: string, promo_revenue: float, regular_revenue: float, promo_orders: int, regular_orders: int}> */
    public array $monthlyTrend = [];

    /** @var array<string, mixed> */
    public array $chartData = [];

    /** @var array{summary: array<string, mixed>, weekly_trend: array<int, array<string, mixed>>, top_products: array<int, array<string, mixed>>} */
    public array $personalisedOffers = [
        'summary' => [],
        'weekly_trend' => [],
        'top_products' => [],
    ];

    public function mount(): void
    {
        if (! $this->initializeBrandContext()) {
            $this->error = 'You do not have access to this brand.';
            $this->loading = false;

            return;
        }

        $this->loadData();
    }

    protected function onBrandContextChanged(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        if (! $this->brandId) {
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

            // Load marketing analytics from BigQuery
            $data = $bq->getMarketingAnalytics($brand->name, $this->monthsBack);

            $this->summaryStats = $data['summary'];
            $this->campaigns = $data['campaigns'];
            $this->discountAnalysis = $data['discount_analysis'];
            $this->monthlyTrend = $data['monthly_trend'];

            // Load promo campaigns (coupon codes)
            $this->promoCampaigns = $bq->getPromoCampaigns($brand->name, $this->monthsBack, 20);

            // Load personalised offers data
            $this->personalisedOffers = $bq->getPersonalisedOffers($brand->name, $this->monthsBack);

            // Build chart data
            $this->chartData = $this->buildChartData();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load marketing data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build chart data for promo vs regular revenue visualization.
     *
     * @return array<string, mixed>
     */
    private function buildChartData(): array
    {
        $labels = [];
        $promoRevenue = [];
        $regularRevenue = [];
        $promoOrders = [];
        $regularOrders = [];

        foreach ($this->monthlyTrend as $row) {
            $labels[] = $row['month'];
            $promoRevenue[] = $row['promo_revenue'];
            $regularRevenue[] = $row['regular_revenue'];
            $promoOrders[] = $row['promo_orders'];
            $regularOrders[] = $row['regular_orders'];
        }

        return [
            'labels' => $labels,
            'revenue' => [
                'datasets' => [
                    [
                        'label' => 'Promo Revenue',
                        'data' => $promoRevenue,
                        'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                        'borderColor' => '#f59e0b',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => 'Regular Revenue',
                        'data' => $regularRevenue,
                        'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                        'borderColor' => '#3b82f6',
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'orders' => [
                'datasets' => [
                    [
                        'label' => 'Promo Orders',
                        'data' => $promoOrders,
                        'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                        'borderColor' => '#f59e0b',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => 'Regular Orders',
                        'data' => $regularOrders,
                        'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                        'borderColor' => '#3b82f6',
                        'borderWidth' => 1,
                    ],
                ],
            ],
        ];
    }

    public function updatedBrandId(): void
    {
        $this->loadData();
    }

    public function updatedMonthsBack(): void
    {
        $this->loadData();
    }

    /**
     * Get time period options.
     *
     * @return array<int, string>
     */
    public function getMonthsBackOptions(): array
    {
        return [
            3 => 'Last 3 Months',
            6 => 'Last 6 Months',
            12 => 'Last 12 Months',
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
     * Get ROI color class.
     */
    public function getRoiColorClass(float $roi): string
    {
        if ($roi >= 2.0) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($roi >= 1.5) {
            return 'text-emerald-600 dark:text-emerald-400';
        }
        if ($roi >= 1.0) {
            return 'text-yellow-600 dark:text-yellow-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Get lift color class.
     */
    public function getLiftColorClass(float $lift): string
    {
        if ($lift >= 30) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($lift >= 15) {
            return 'text-emerald-600 dark:text-emerald-400';
        }
        if ($lift >= 0) {
            return 'text-yellow-600 dark:text-yellow-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Get campaign status badge class.
     */
    public function getCampaignStatusClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'scheduled' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        };
    }

    /**
     * Get discount tier color.
     */
    public function getDiscountTierColor(string $tier): string
    {
        return match ($tier) {
            '0-10%' => 'bg-green-500',
            '10-20%' => 'bg-emerald-500',
            '20-30%' => 'bg-yellow-500',
            '30-50%' => 'bg-orange-500',
            '50%+' => 'bg-red-500',
            default => 'bg-gray-500',
        };
    }

    /**
     * Check if has campaign data.
     */
    public function hasCampaignData(): bool
    {
        return ! empty($this->campaigns);
    }

    /**
     * Check if has promo campaigns data.
     */
    public function hasPromoCampaigns(): bool
    {
        return ! empty($this->promoCampaigns);
    }

    /**
     * Format date for display.
     */
    public function formatDate(string $date): string
    {
        if (empty($date)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Exception) {
            return $date;
        }
    }

    /**
     * Check if has personalised offers data.
     */
    public function hasPersonalisedOffers(): bool
    {
        return ! empty($this->personalisedOffers['summary']) && ($this->personalisedOffers['summary']['total_offers'] ?? 0) > 0;
    }

    /**
     * Get personalised offers summary.
     *
     * @return array<string, mixed>
     */
    public function getPersonalisedOffersSummary(): array
    {
        return $this->personalisedOffers['summary'] ?? [];
    }

    /**
     * Get personalised offers weekly trend.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPersonalisedOffersWeeklyTrend(): array
    {
        return $this->personalisedOffers['weekly_trend'] ?? [];
    }

    /**
     * Get top products in personalised offers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPersonalisedOffersTopProducts(): array
    {
        return $this->personalisedOffers['top_products'] ?? [];
    }
}
