<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Filament\SupplyPanel\Concerns\HasBrandContext;
use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Forecasting extends Page
{
    use HasBrandContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Forecasting';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.supply-panel.pages.forecasting';

    #[Url]
    public string $scenario = 'baseline';

    #[Url]
    public int $forecastMonths = 6;

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array<string, mixed>> */
    public array $historicalData = [];

    /** @var array<int, array<string, mixed>> */
    public array $forecastData = [];

    /** @var array<string, mixed> */
    public array $chartData = [];

    /** @var array<string, mixed> */
    public array $summaryStats = [];

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

            // Load forecast data from BigQuery
            $data = $bq->getSalesForecast($brand->name, 12, $this->forecastMonths);

            $this->historicalData = $data['historical'];
            $this->forecastData = $data['forecast'];

            // Build chart data
            $this->chartData = $this->buildChartData();

            // Calculate summary statistics
            $this->summaryStats = $this->calculateSummaryStats();

            $this->loading = false;

            // Dispatch event to initialize/update chart
            $this->dispatch('forecast-data-updated', chartData: $this->chartData);
        } catch (\Exception $e) {
            $this->error = 'Failed to load forecast data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build chart data for Chart.js.
     *
     * @return array<string, mixed>
     */
    private function buildChartData(): array
    {
        $labels = [];
        $historicalRevenue = [];
        $baselineRevenue = [];
        $optimisticRevenue = [];
        $pessimisticRevenue = [];
        $lowerBound = [];
        $upperBound = [];

        // Historical data
        foreach ($this->historicalData as $data) {
            $labels[] = $data['month'];
            $historicalRevenue[] = $data['revenue'];
            $baselineRevenue[] = null;
            $optimisticRevenue[] = null;
            $pessimisticRevenue[] = null;
            $lowerBound[] = null;
            $upperBound[] = null;
        }

        // Forecast data
        foreach ($this->forecastData as $data) {
            $labels[] = $data['month'];
            $historicalRevenue[] = null;
            $baselineRevenue[] = $data['baseline'];
            $optimisticRevenue[] = $data['optimistic'];
            $pessimisticRevenue[] = $data['pessimistic'];
            $lowerBound[] = $data['lower_bound'];
            $upperBound[] = $data['upper_bound'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Historical',
                    'data' => $historicalRevenue,
                    'borderColor' => '#006654',
                    'backgroundColor' => 'rgba(0, 102, 84, 0.1)',
                    'fill' => false,
                    'tension' => 0.1,
                ],
                [
                    'label' => 'Baseline Forecast',
                    'data' => $baselineRevenue,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderDash' => [5, 5],
                    'fill' => false,
                    'tension' => 0.1,
                ],
                [
                    'label' => 'Optimistic',
                    'data' => $optimisticRevenue,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderDash' => [2, 2],
                    'fill' => false,
                    'tension' => 0.1,
                    'hidden' => $this->scenario !== 'optimistic',
                ],
                [
                    'label' => 'Pessimistic',
                    'data' => $pessimisticRevenue,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderDash' => [2, 2],
                    'fill' => false,
                    'tension' => 0.1,
                    'hidden' => $this->scenario !== 'pessimistic',
                ],
                [
                    'label' => 'Confidence Interval',
                    'data' => $upperBound,
                    'borderColor' => 'rgba(59, 130, 246, 0.3)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => '+1',
                    'tension' => 0.1,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Lower Bound',
                    'data' => $lowerBound,
                    'borderColor' => 'rgba(59, 130, 246, 0.3)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => false,
                    'tension' => 0.1,
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    /**
     * Calculate summary statistics.
     *
     * @return array<string, mixed>
     */
    private function calculateSummaryStats(): array
    {
        if (empty($this->historicalData) || empty($this->forecastData)) {
            return [
                'avg_historical' => 0,
                'total_forecast_baseline' => 0,
                'total_forecast_optimistic' => 0,
                'total_forecast_pessimistic' => 0,
                'growth_rate' => null,
                'trend' => 'stable',
            ];
        }

        // Calculate historical average
        $historicalSum = array_sum(array_column($this->historicalData, 'revenue'));
        $avgHistorical = $historicalSum / count($this->historicalData);

        // Calculate forecast totals
        $totalBaseline = array_sum(array_column($this->forecastData, 'baseline'));
        $totalOptimistic = array_sum(array_column($this->forecastData, 'optimistic'));
        $totalPessimistic = array_sum(array_column($this->forecastData, 'pessimistic'));

        // Calculate growth rate (last historical vs first forecast)
        $lastHistorical = end($this->historicalData)['revenue'] ?? 0;
        $firstForecast = $this->forecastData[0]['baseline'] ?? 0;
        $growthRate = $lastHistorical > 0
            ? (($firstForecast - $lastHistorical) / $lastHistorical) * 100
            : null;

        // Determine trend
        $trend = 'stable';
        if ($growthRate !== null) {
            if ($growthRate > 5) {
                $trend = 'growing';
            } elseif ($growthRate < -5) {
                $trend = 'declining';
            }
        }

        return [
            'avg_historical' => round($avgHistorical, 2),
            'total_forecast_baseline' => round($totalBaseline, 2),
            'total_forecast_optimistic' => round($totalOptimistic, 2),
            'total_forecast_pessimistic' => round($totalPessimistic, 2),
            'growth_rate' => $growthRate !== null ? round($growthRate, 1) : null,
            'trend' => $trend,
        ];
    }

    public function updatedBrandId(): void
    {
        $this->loadData();
    }

    public function updatedScenario(): void
    {
        // Rebuild chart data with new scenario visibility
        $this->chartData = $this->buildChartData();
    }

    public function updatedForecastMonths(): void
    {
        $this->loadData();
    }

    /**
     * Get scenario options.
     *
     * @return array<string, string>
     */
    public function getScenarioOptions(): array
    {
        return [
            'baseline' => 'Baseline',
            'optimistic' => 'Optimistic (+15%)',
            'pessimistic' => 'Pessimistic (-10%)',
            'all' => 'All Scenarios',
        ];
    }

    /**
     * Get forecast period options.
     *
     * @return array<int, string>
     */
    public function getForecastPeriodOptions(): array
    {
        return [
            3 => '3 Months',
            6 => '6 Months',
            12 => '12 Months',
        ];
    }

    /**
     * Get trend icon.
     */
    public function getTrendIcon(): string
    {
        return match ($this->summaryStats['trend'] ?? 'stable') {
            'growing' => '↑',
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
            'growing' => 'text-green-600 dark:text-green-400',
            'declining' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    /**
     * Get the selected scenario's forecast total.
     */
    public function getSelectedScenarioTotal(): float
    {
        return match ($this->scenario) {
            'optimistic' => $this->summaryStats['total_forecast_optimistic'] ?? 0,
            'pessimistic' => $this->summaryStats['total_forecast_pessimistic'] ?? 0,
            default => $this->summaryStats['total_forecast_baseline'] ?? 0,
        };
    }

    /**
     * Format currency value.
     */
    public function formatCurrency(float $value): string
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
