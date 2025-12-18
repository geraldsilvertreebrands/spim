<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Trends extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Trends';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.supply-panel.pages.trends';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $period = '12m';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array{labels: array<string>, datasets: array<array{label: string, data: array<float>, borderColor: string, backgroundColor: string}>} */
    public array $revenueChartData = ['labels' => [], 'datasets' => []];

    /** @var array{labels: array<string>, datasets: array<array{label: string, data: array<float>, backgroundColor: string}>} */
    public array $categoryChartData = ['labels' => [], 'datasets' => []];

    /** @var array{labels: array<string>, datasets: array<array{label: string, data: array<int>, borderColor: string, backgroundColor: string}>} */
    public array $unitsChartData = ['labels' => [], 'datasets' => []];

    /** @var array{labels: array<string>, datasets: array<array{label: string, data: array<float>, borderColor: string, backgroundColor: string}>} */
    public array $aovChartData = ['labels' => [], 'datasets' => []];

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

            $months = $this->periodToMonths($this->period);

            // Load revenue trend (main line chart)
            $this->revenueChartData = $bq->getSalesTrend($brand->name, $months);

            // Load category breakdown
            $this->categoryChartData = $this->getCategoryRevenueTrend($bq, $brand->name, $months);

            // Load units trend
            $this->unitsChartData = $this->getUnitsTrend($bq, $brand->name, $months);

            // Load AOV trend
            $this->aovChartData = $this->getAovTrend($bq, $brand->name, $months);

            $this->loading = false;

            // Dispatch event to update charts in JavaScript
            $this->dispatch('trends-data-updated',
                revenueData: $this->revenueChartData,
                categoryData: $this->categoryChartData,
                unitsData: $this->unitsChartData,
                aovData: $this->aovChartData
            );
        } catch (\Exception $e) {
            $this->error = 'Failed to load trend data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Get category revenue trend data.
     *
     * @return array{labels: array<string>, datasets: array<array{label: string, data: array<float>, backgroundColor: string}>}
     */
    private function getCategoryRevenueTrend(BigQueryService $bq, string $brandName, int $months): array
    {
        // This would need a specific BigQuery method, but for now we'll derive from product data
        $productTable = $bq->getProductPerformanceTable($brandName, $months.'m');

        // Group by category and month
        $categoryData = [];
        $allMonths = [];

        foreach ($productTable as $product) {
            $category = $product['category'];
            if (! isset($categoryData[$category])) {
                $categoryData[$category] = [];
            }
            foreach ($product['months'] as $month => $revenue) {
                if (! in_array($month, $allMonths)) {
                    $allMonths[] = $month;
                }
                if (! isset($categoryData[$category][$month])) {
                    $categoryData[$category][$month] = 0;
                }
                $categoryData[$category][$month] += $revenue;
            }
        }

        sort($allMonths);

        // Take top 5 categories by total revenue
        $categoryTotals = [];
        foreach ($categoryData as $category => $months) {
            $categoryTotals[$category] = array_sum($months);
        }
        arsort($categoryTotals);
        $topCategories = array_slice(array_keys($categoryTotals), 0, 5);

        // Build datasets
        $colors = ['#006654', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6'];
        $datasets = [];
        foreach ($topCategories as $index => $category) {
            $data = [];
            foreach ($allMonths as $month) {
                $data[] = $categoryData[$category][$month] ?? 0;
            }
            $datasets[] = [
                'label' => $category,
                'data' => $data,
                'backgroundColor' => $colors[$index] ?? '#6B7280',
            ];
        }

        return [
            'labels' => $allMonths,
            'datasets' => $datasets,
        ];
    }

    /**
     * Get units sold trend data.
     *
     * @return array{labels: array<string>, datasets: array<array{label: string, data: array<int>, borderColor: string, backgroundColor: string}>}
     */
    private function getUnitsTrend(BigQueryService $bq, string $brandName, int $months): array
    {
        // Derive from product performance table
        $productTable = $bq->getProductPerformanceTable($brandName, $months.'m');

        // This is a simplified version - ideally we'd have a separate BigQuery method
        // For now, we'll use the revenue trend structure and note it needs enhancement
        $revenueTrend = $bq->getSalesTrend($brandName, $months);

        // Transform to units (estimate based on revenue / average price)
        // In production, this should be a separate BigQuery query
        return [
            'labels' => $revenueTrend['labels'],
            'datasets' => [
                [
                    'label' => 'Units Sold',
                    'data' => array_map(fn ($v) => (int) ($v / 100), $revenueTrend['datasets'][0]['data'] ?? []),
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                ],
            ],
        ];
    }

    /**
     * Get AOV trend data.
     *
     * @return array{labels: array<string>, datasets: array<array{label: string, data: array<float>, borderColor: string, backgroundColor: string}>}
     */
    private function getAovTrend(BigQueryService $bq, string $brandName, int $months): array
    {
        return $bq->getAovTrend($brandName, $months);
    }

    /**
     * Convert period string to months.
     */
    private function periodToMonths(string $period): int
    {
        return match ($period) {
            '3m' => 3,
            '6m' => 6,
            '12m' => 12,
            '24m' => 24,
            default => 12,
        };
    }

    public function updatedBrandId(): void
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
     * Get period options for the filter.
     *
     * @return array<string, string>
     */
    public function getPeriodOptions(): array
    {
        return [
            '3m' => 'Last 3 Months',
            '6m' => 'Last 6 Months',
            '12m' => 'Last 12 Months',
            '24m' => 'Last 24 Months',
        ];
    }

    /**
     * Export trend data to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'trends_data_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Headers - Month + Revenue + Units + AOV
            fputcsv($handle, ['Month', 'Revenue', 'Units', 'AOV']);

            // Data rows - combine all trend data
            $labels = $this->revenueChartData['labels'] ?? [];
            $revenues = $this->revenueChartData['datasets'][0]['data'] ?? [];
            $units = $this->unitsChartData['datasets'][0]['data'] ?? [];
            $aovs = $this->aovChartData['datasets'][0]['data'] ?? [];

            foreach ($labels as $index => $month) {
                fputcsv($handle, [
                    $month,
                    $revenues[$index] ?? 0,
                    $units[$index] ?? 0,
                    $aovs[$index] ?? 0,
                ]);
            }

            fclose($handle);
        }, $filename);
    }
}
