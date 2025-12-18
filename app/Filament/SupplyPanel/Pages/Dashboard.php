<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Dashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.supply-panel.pages.dashboard';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $period = '30d';

    public array $kpis = [];

    public array $chartData = [];

    public array $topProducts = [];

    public bool $loading = true;

    public ?string $error = null;

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
