<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class RfmAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'RFM Analysis';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.supply-panel.pages.rfm-analysis';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public int $monthsBack = 12;

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<string, array<string, mixed>> */
    public array $segments = [];

    /** @var array<string, mixed> */
    public array $summaryStats = [];

    /** @var array<int, array{r_score: int, f_score: int, m_score: int, count: int}> */
    public array $rfmMatrix = [];

    public function mount(): void
    {
        // Default to user's first brand if not specified
        if (! $this->brandId) {
            $this->brandId = auth()->user()->accessibleBrandIds()[0] ?? null;
        }

        // Verify user can access this brand
        if ($this->brandId) {
            $brand = Brand::find($this->brandId);
            if (! $brand || ! auth()->user()->canAccessBrand($brand)) {
                $this->error = 'You do not have access to this brand.';
                $this->loading = false;

                return;
            }
        }

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

            // Load RFM analysis data from BigQuery
            $data = $bq->getRfmAnalysis($brand->name, $this->monthsBack);

            $this->segments = $data['segments'];
            $this->rfmMatrix = $data['matrix'];

            // Calculate summary statistics
            $this->summaryStats = $this->calculateSummaryStats();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load RFM data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Calculate summary statistics from RFM data.
     *
     * @return array<string, mixed>
     */
    private function calculateSummaryStats(): array
    {
        if (empty($this->segments)) {
            return [
                'total_customers' => 0,
                'champions_count' => 0,
                'champions_pct' => 0,
                'at_risk_count' => 0,
                'at_risk_pct' => 0,
                'avg_recency_score' => 0,
                'avg_frequency_score' => 0,
                'avg_monetary_score' => 0,
            ];
        }

        $totalCustomers = 0;
        $championsCount = 0;
        $atRiskCount = 0;

        foreach ($this->segments as $segmentName => $segment) {
            $count = $segment['count'] ?? 0;
            $totalCustomers += $count;

            if ($segmentName === 'Champions') {
                $championsCount = $count;
            }
            if (in_array($segmentName, ['At Risk', 'Hibernating', 'Lost'])) {
                $atRiskCount += $count;
            }
        }

        // Calculate average scores from matrix
        $totalR = 0;
        $totalF = 0;
        $totalM = 0;
        $matrixCount = 0;

        foreach ($this->rfmMatrix as $row) {
            $totalR += $row['r_score'] * $row['count'];
            $totalF += $row['f_score'] * $row['count'];
            $totalM += $row['m_score'] * $row['count'];
            $matrixCount += $row['count'];
        }

        return [
            'total_customers' => $totalCustomers,
            'champions_count' => $championsCount,
            'champions_pct' => $totalCustomers > 0 ? round(($championsCount / $totalCustomers) * 100, 1) : 0,
            'at_risk_count' => $atRiskCount,
            'at_risk_pct' => $totalCustomers > 0 ? round(($atRiskCount / $totalCustomers) * 100, 1) : 0,
            'avg_recency_score' => $matrixCount > 0 ? round($totalR / $matrixCount, 1) : 0,
            'avg_frequency_score' => $matrixCount > 0 ? round($totalF / $matrixCount, 1) : 0,
            'avg_monetary_score' => $matrixCount > 0 ? round($totalM / $matrixCount, 1) : 0,
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
     * Get available brands for the current user.
     *
     * @return array<int, string>
     */
    public function getAvailableBrands(): array
    {
        $user = auth()->user();
        $brandIds = $user->accessibleBrandIds();

        return Brand::whereIn('id', $brandIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get period options.
     *
     * @return array<int, string>
     */
    public function getPeriodOptions(): array
    {
        return [
            6 => 'Last 6 Months',
            12 => 'Last 12 Months',
            18 => 'Last 18 Months',
            24 => 'Last 24 Months',
        ];
    }

    /**
     * Get segment definitions.
     *
     * @return array<string, array{description: string, color: string, bgColor: string, action: string}>
     */
    public function getSegmentDefinitions(): array
    {
        return [
            'Champions' => [
                'description' => 'Best customers - bought recently, buy often, spend most',
                'color' => 'text-green-700 dark:text-green-300',
                'bgColor' => 'bg-green-100 dark:bg-green-900/30',
                'action' => 'Reward them, ask for reviews, upsell premium',
            ],
            'Loyal Customers' => [
                'description' => 'High frequency buyers with good spending',
                'color' => 'text-emerald-700 dark:text-emerald-300',
                'bgColor' => 'bg-emerald-100 dark:bg-emerald-900/30',
                'action' => 'Loyalty programs, exclusive offers',
            ],
            'Potential Loyalists' => [
                'description' => 'Recent customers with potential to become loyal',
                'color' => 'text-teal-700 dark:text-teal-300',
                'bgColor' => 'bg-teal-100 dark:bg-teal-900/30',
                'action' => 'Encourage repeat purchases, membership offers',
            ],
            'New Customers' => [
                'description' => 'Recently acquired, low frequency so far',
                'color' => 'text-blue-700 dark:text-blue-300',
                'bgColor' => 'bg-blue-100 dark:bg-blue-900/30',
                'action' => 'Onboarding, welcome offers, product education',
            ],
            'Promising' => [
                'description' => 'Recent shoppers with average frequency/monetary',
                'color' => 'text-cyan-700 dark:text-cyan-300',
                'bgColor' => 'bg-cyan-100 dark:bg-cyan-900/30',
                'action' => 'Create brand awareness, free trials',
            ],
            'Need Attention' => [
                'description' => 'Above average customers showing declining activity',
                'color' => 'text-yellow-700 dark:text-yellow-300',
                'bgColor' => 'bg-yellow-100 dark:bg-yellow-900/30',
                'action' => 'Re-engagement campaigns, limited offers',
            ],
            'About to Sleep' => [
                'description' => 'Below average recency and frequency',
                'color' => 'text-orange-700 dark:text-orange-300',
                'bgColor' => 'bg-orange-100 dark:bg-orange-900/30',
                'action' => 'Win-back campaigns, personalized reactivation',
            ],
            'At Risk' => [
                'description' => 'Spent big money, but long time ago',
                'color' => 'text-red-700 dark:text-red-300',
                'bgColor' => 'bg-red-100 dark:bg-red-900/30',
                'action' => 'Personalized outreach, special discounts',
            ],
            'Hibernating' => [
                'description' => 'Last purchase was a long time ago, low spenders',
                'color' => 'text-gray-700 dark:text-gray-300',
                'bgColor' => 'bg-gray-100 dark:bg-gray-800',
                'action' => 'Reactivation campaigns or remove from active lists',
            ],
            'Lost' => [
                'description' => 'Lowest recency, frequency, and monetary',
                'color' => 'text-gray-500 dark:text-gray-400',
                'bgColor' => 'bg-gray-50 dark:bg-gray-900',
                'action' => 'Ignore or attempt one final win-back',
            ],
        ];
    }

    /**
     * Get segment color class.
     */
    public function getSegmentColor(string $segment): string
    {
        $definitions = $this->getSegmentDefinitions();

        return $definitions[$segment]['color'] ?? 'text-gray-600 dark:text-gray-400';
    }

    /**
     * Get segment background color class.
     */
    public function getSegmentBgColor(string $segment): string
    {
        $definitions = $this->getSegmentDefinitions();

        return $definitions[$segment]['bgColor'] ?? 'bg-gray-100 dark:bg-gray-800';
    }

    /**
     * Get chart data for segment distribution.
     *
     * @return array<string, mixed>
     */
    public function getChartData(): array
    {
        $labels = [];
        $data = [];
        $colors = [
            'Champions' => '#059669',
            'Loyal Customers' => '#10b981',
            'Potential Loyalists' => '#14b8a6',
            'New Customers' => '#3b82f6',
            'Promising' => '#06b6d4',
            'Need Attention' => '#eab308',
            'About to Sleep' => '#f97316',
            'At Risk' => '#ef4444',
            'Hibernating' => '#6b7280',
            'Lost' => '#9ca3af',
        ];

        foreach ($this->segments as $segmentName => $segment) {
            $labels[] = $segmentName;
            $data[] = $segment['count'] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_map(
                        fn ($label) => $colors[$label] ?? '#6b7280',
                        $labels
                    ),
                ],
            ],
        ];
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
     * Format currency.
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
}
