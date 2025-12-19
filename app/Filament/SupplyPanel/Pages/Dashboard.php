<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Filament\SupplyPanel\Concerns\HasBrandContext;
use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Dashboard extends Page
{
    use HasBrandContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.supply-panel.pages.dashboard';

    #[Url]
    public string $period = '30d';

    public array $kpis = [];

    public array $chartData = [];

    public array $topProducts = [];

    public bool $loading = true;

    public ?string $error = null;

    public bool $showChartAsTable = false;

    /**
     * Toggle between chart and table view.
     */
    public function toggleChartView(): void
    {
        $this->showChartAsTable = ! $this->showChartAsTable;

        // If switching back to chart view, dispatch event to re-initialize chart
        if (! $this->showChartAsTable) {
            $this->dispatch('dashboard-data-updated', chartData: $this->chartData);
        }
    }

    /**
     * Get chart data formatted for table display.
     *
     * @return array<int, array{month: string, revenue: float}>
     */
    public function getChartTableData(): array
    {
        if (empty($this->chartData['labels']) || empty($this->chartData['datasets'])) {
            return [];
        }

        $data = [];
        $labels = $this->chartData['labels'];
        $values = $this->chartData['datasets'][0]['data'] ?? [];

        foreach ($labels as $index => $month) {
            $data[] = [
                'month' => $month,
                'revenue' => $values[$index] ?? 0,
            ];
        }

        return $data;
    }

    public function mount(): void
    {
        // Initialize brand from sidebar/session/URL
        if (! $this->initializeBrandContext()) {
            $this->error = 'You do not have access to this brand.';
            $this->loading = false;

            return;
        }

        $this->loadData();
    }

    /**
     * Handle brand change from sidebar selector.
     */
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

            // Load KPIs
            $this->kpis = $bq->getBrandKpis($brand->name, $this->period);

            // Load chart data
            $this->chartData = $bq->getSalesTrend($brand->name, 12);

            // Load top products
            $this->topProducts = $bq->getTopProducts($brand->name, 5, $this->period);

            $this->loading = false;

            // Dispatch event to update charts in JavaScript
            $this->dispatch('dashboard-data-updated', chartData: $this->chartData);
        } catch (\Exception $e) {
            $this->error = 'Failed to load dashboard data: '.$e->getMessage();
            $this->loading = false;
        }
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
     * Get period options for the filter.
     *
     * @return array<string, string>
     */
    public function getPeriodOptions(): array
    {
        return [
            '30d' => 'Last 30 Days',
            '90d' => 'Last 90 Days',
            '1yr' => 'Last Year',
        ];
    }

    /**
     * Export top products to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'dashboard_top_products_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Headers
            fputcsv($handle, ['SKU', 'Name', 'Revenue', 'Units']);

            // Data rows
            foreach ($this->topProducts as $product) {
                fputcsv($handle, [
                    $product['sku'] ?? '',
                    $product['name'] ?? '',
                    $product['revenue'] ?? 0,
                    $product['units'] ?? 0,
                ]);
            }

            fclose($handle);
        }, $filename);
    }
}
