<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Products extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.supply-panel.pages.products';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $period = '12m';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<string> */
    public array $months = [];

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

            // Load product performance table with monthly breakdown
            $rawProducts = $bq->getProductPerformanceTable($brand->name, $this->period);

            // Extract months from the data
            $this->months = [];
            foreach ($rawProducts as $product) {
                foreach (array_keys($product['months']) as $month) {
                    if (! in_array($month, $this->months)) {
                        $this->months[] = $month;
                    }
                }
            }
            sort($this->months);

            // Format products for display
            $this->products = $rawProducts;
            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load product data: '.$e->getMessage();
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
            '3m' => 'Last 3 Months',
            '6m' => 'Last 6 Months',
            '12m' => 'Last 12 Months',
        ];
    }

    /**
     * Export products to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'products_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Headers
            $headers = ['SKU', 'Name', 'Category'];
            foreach ($this->months as $month) {
                $headers[] = $month;
            }
            $headers[] = 'Total';
            fputcsv($handle, $headers);

            // Data rows
            foreach ($this->products as $product) {
                $row = [
                    $product['sku'] ?? '',
                    $product['name'] ?? '',
                    $product['category'] ?? '',
                ];
                foreach ($this->months as $month) {
                    $row[] = $product['months'][$month] ?? 0;
                }
                $row[] = $product['total'] ?? 0;
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename);
    }
}
