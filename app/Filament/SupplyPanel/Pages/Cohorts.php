<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Cohorts extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Cohort Analysis';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.supply-panel.pages.cohorts';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public int $monthsBack = 12;

    #[Url]
    public string $metric = 'retention';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<string, array<string, mixed>> */
    public array $cohortData = [];

    /** @var array<string> */
    public array $cohortMonths = [];

    /** @var array<string, mixed> */
    public array $summaryStats = [];

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

            // Load cohort analysis data from BigQuery
            $data = $bq->getCohortAnalysis($brand->name, $this->monthsBack);

            $this->cohortData = $data['cohorts'];
            $this->cohortMonths = $data['months'];

            // Calculate summary statistics
            $this->summaryStats = $this->calculateSummaryStats();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load cohort data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Calculate summary statistics from cohort data.
     *
     * @return array<string, mixed>
     */
    private function calculateSummaryStats(): array
    {
        if (empty($this->cohortData)) {
            return [
                'total_cohorts' => 0,
                'avg_month1_retention' => 0,
                'avg_month3_retention' => 0,
                'avg_month6_retention' => 0,
                'best_cohort' => null,
                'worst_cohort' => null,
                'overall_retention_trend' => 'stable',
            ];
        }

        $totalCohorts = count($this->cohortData);
        $month1Retentions = [];
        $month3Retentions = [];
        $month6Retentions = [];

        foreach ($this->cohortData as $cohortMonth => $cohort) {
            // Month 1 retention (index 1, since index 0 is the acquisition month)
            if (isset($cohort['retention'][1])) {
                $month1Retentions[$cohortMonth] = $cohort['retention'][1];
            }
            if (isset($cohort['retention'][3])) {
                $month3Retentions[$cohortMonth] = $cohort['retention'][3];
            }
            if (isset($cohort['retention'][6])) {
                $month6Retentions[$cohortMonth] = $cohort['retention'][6];
            }
        }

        // Find best and worst cohorts by month 1 retention
        $bestCohort = null;
        $worstCohort = null;
        $bestRetention = 0;
        $worstRetention = 100;

        foreach ($month1Retentions as $month => $retention) {
            if ($retention > $bestRetention) {
                $bestRetention = $retention;
                $bestCohort = $month;
            }
            if ($retention < $worstRetention) {
                $worstRetention = $retention;
                $worstCohort = $month;
            }
        }

        // Calculate retention trend
        $trend = 'stable';
        if (count($month1Retentions) >= 4) {
            $sortedMonths = array_keys($month1Retentions);
            sort($sortedMonths);
            $half = intval(count($sortedMonths) / 2);

            $firstHalfAvg = 0;
            $secondHalfAvg = 0;
            $firstHalfCount = 0;
            $secondHalfCount = 0;

            foreach ($sortedMonths as $i => $month) {
                if ($i < $half) {
                    $firstHalfAvg += $month1Retentions[$month];
                    $firstHalfCount++;
                } else {
                    $secondHalfAvg += $month1Retentions[$month];
                    $secondHalfCount++;
                }
            }

            if ($firstHalfCount > 0 && $secondHalfCount > 0) {
                $firstHalfAvg /= $firstHalfCount;
                $secondHalfAvg /= $secondHalfCount;

                $diff = $secondHalfAvg - $firstHalfAvg;
                if ($diff > 5) {
                    $trend = 'improving';
                } elseif ($diff < -5) {
                    $trend = 'declining';
                }
            }
        }

        return [
            'total_cohorts' => $totalCohorts,
            'avg_month1_retention' => count($month1Retentions) > 0
                ? round(array_sum($month1Retentions) / count($month1Retentions), 1)
                : 0,
            'avg_month3_retention' => count($month3Retentions) > 0
                ? round(array_sum($month3Retentions) / count($month3Retentions), 1)
                : 0,
            'avg_month6_retention' => count($month6Retentions) > 0
                ? round(array_sum($month6Retentions) / count($month6Retentions), 1)
                : 0,
            'best_cohort' => $bestCohort,
            'worst_cohort' => $worstCohort,
            'overall_retention_trend' => $trend,
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

    public function updatedMetric(): void
    {
        // Rebuilds view with different metric emphasis
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
     * Get metric options.
     *
     * @return array<string, string>
     */
    public function getMetricOptions(): array
    {
        return [
            'retention' => 'Retention Rate (%)',
            'customers' => 'Active Customers',
            'revenue' => 'Revenue per Customer',
        ];
    }

    /**
     * Get retention color class based on percentage.
     */
    public function getRetentionColorClass(float $retention): string
    {
        if ($retention >= 50) {
            return 'bg-green-600 text-white';
        }
        if ($retention >= 30) {
            return 'bg-green-400 text-white';
        }
        if ($retention >= 20) {
            return 'bg-yellow-400 text-gray-900';
        }
        if ($retention >= 10) {
            return 'bg-orange-400 text-white';
        }

        return 'bg-red-400 text-white';
    }

    /**
     * Get trend icon.
     */
    public function getTrendIcon(): string
    {
        return match ($this->summaryStats['overall_retention_trend'] ?? 'stable') {
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
        return match ($this->summaryStats['overall_retention_trend'] ?? 'stable') {
            'improving' => 'text-green-600 dark:text-green-400',
            'declining' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
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
