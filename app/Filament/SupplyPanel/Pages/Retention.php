<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Retention extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Retention';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.supply-panel.pages.retention';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public int $monthsBack = 12;

    #[Url]
    public string $period = 'monthly';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array{month: string, retained: int, churned: int, retention_rate: float, churn_rate: float}> */
    public array $retentionData = [];

    /** @var array<string, mixed> */
    public array $summaryStats = [];

    /** @var array<string, mixed> */
    public array $chartData = [];

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

            // Load retention analysis data from BigQuery
            $data = $bq->getRetentionAnalysis($brand->name, $this->monthsBack, $this->period);

            $this->retentionData = $data['retention'];

            // Calculate summary statistics
            $this->summaryStats = $this->calculateSummaryStats($data);

            // Build chart data
            $this->chartData = $this->buildChartData();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load retention data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Calculate summary statistics from retention data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function calculateSummaryStats(array $data): array
    {
        if (empty($this->retentionData)) {
            return [
                'avg_retention_rate' => 0,
                'avg_churn_rate' => 0,
                'total_retained' => 0,
                'total_churned' => 0,
                'best_period' => null,
                'worst_period' => null,
                'trend' => 'stable',
                'current_retention' => 0,
                'previous_retention' => 0,
                'retention_change' => 0,
            ];
        }

        $totalRetained = 0;
        $totalChurned = 0;
        $retentionRates = [];
        $churnRates = [];

        foreach ($this->retentionData as $row) {
            $totalRetained += $row['retained'];
            $totalChurned += $row['churned'];
            $retentionRates[$row['month']] = $row['retention_rate'];
            $churnRates[$row['month']] = $row['churn_rate'];
        }

        // Find best and worst periods
        $bestPeriod = null;
        $worstPeriod = null;
        $bestRate = 0;
        $worstRate = 100;

        foreach ($retentionRates as $period => $rate) {
            if ($rate > $bestRate) {
                $bestRate = $rate;
                $bestPeriod = $period;
            }
            if ($rate < $worstRate) {
                $worstRate = $rate;
                $worstPeriod = $period;
            }
        }

        // Calculate trend (compare recent vs older periods)
        $trend = 'stable';
        $periods = array_keys($retentionRates);
        if (count($periods) >= 4) {
            $half = intval(count($periods) / 2);
            $firstHalfAvg = array_sum(array_slice($retentionRates, 0, $half)) / $half;
            $secondHalfAvg = array_sum(array_slice($retentionRates, $half)) / (count($periods) - $half);

            $diff = $secondHalfAvg - $firstHalfAvg;
            if ($diff > 3) {
                $trend = 'improving';
            } elseif ($diff < -3) {
                $trend = 'declining';
            }
        }

        // Get current and previous period for comparison
        $currentRetention = 0;
        $previousRetention = 0;
        if (count($this->retentionData) >= 2) {
            $currentRetention = $this->retentionData[count($this->retentionData) - 1]['retention_rate'];
            $previousRetention = $this->retentionData[count($this->retentionData) - 2]['retention_rate'];
        } elseif (count($this->retentionData) === 1) {
            $currentRetention = $this->retentionData[0]['retention_rate'];
        }

        $retentionChange = $currentRetention - $previousRetention;

        return [
            'avg_retention_rate' => round(array_sum($retentionRates) / count($retentionRates), 1),
            'avg_churn_rate' => round(array_sum($churnRates) / count($churnRates), 1),
            'total_retained' => $totalRetained,
            'total_churned' => $totalChurned,
            'best_period' => $bestPeriod,
            'worst_period' => $worstPeriod,
            'trend' => $trend,
            'current_retention' => $currentRetention,
            'previous_retention' => $previousRetention,
            'retention_change' => round($retentionChange, 1),
        ];
    }

    /**
     * Build chart data for retention visualization.
     *
     * @return array<string, mixed>
     */
    private function buildChartData(): array
    {
        $labels = [];
        $retentionData = [];
        $churnData = [];

        foreach ($this->retentionData as $row) {
            $labels[] = $row['month'];
            $retentionData[] = $row['retention_rate'];
            $churnData[] = $row['churn_rate'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Retention Rate',
                    'data' => $retentionData,
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Churn Rate',
                    'data' => $churnData,
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
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

    public function updatedPeriod(): void
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
     * Get period granularity options.
     *
     * @return array<string, string>
     */
    public function getPeriodOptions(): array
    {
        return [
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
        ];
    }

    /**
     * Get trend icon.
     */
    public function getTrendIcon(): string
    {
        return match ($this->summaryStats['trend'] ?? 'stable') {
            'improving' => '↑',
            'declining' => '↓',
            default => '→',
        };
    }

    /**
     * Get trend color class.
     */
    public function getTrendColorClass(): string
    {
        return match ($this->summaryStats['trend'] ?? 'stable') {
            'improving' => 'text-green-600 dark:text-green-400',
            'declining' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    /**
     * Get change icon.
     */
    public function getChangeIcon(): string
    {
        $change = $this->summaryStats['retention_change'] ?? 0;
        if ($change > 0) {
            return '↑';
        }
        if ($change < 0) {
            return '↓';
        }

        return '→';
    }

    /**
     * Get change color class.
     */
    public function getChangeColorClass(): string
    {
        $change = $this->summaryStats['retention_change'] ?? 0;
        if ($change > 0) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($change < 0) {
            return 'text-red-600 dark:text-red-400';
        }

        return 'text-gray-600 dark:text-gray-400';
    }

    /**
     * Get retention rate color class.
     */
    public function getRetentionColorClass(float $rate): string
    {
        if ($rate >= 80) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($rate >= 60) {
            return 'text-emerald-600 dark:text-emerald-400';
        }
        if ($rate >= 40) {
            return 'text-yellow-600 dark:text-yellow-400';
        }
        if ($rate >= 20) {
            return 'text-orange-600 dark:text-orange-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Get churn rate color class (inverse of retention).
     */
    public function getChurnColorClass(float $rate): string
    {
        if ($rate <= 20) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($rate <= 40) {
            return 'text-emerald-600 dark:text-emerald-400';
        }
        if ($rate <= 60) {
            return 'text-yellow-600 dark:text-yellow-400';
        }
        if ($rate <= 80) {
            return 'text-orange-600 dark:text-orange-400';
        }

        return 'text-red-600 dark:text-red-400';
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
}
