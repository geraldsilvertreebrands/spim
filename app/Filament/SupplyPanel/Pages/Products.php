<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Filament\SupplyPanel\Concerns\HasBrandContext;
use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Products extends Page
{
    use HasBrandContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.supply-panel.pages.products';

    #[Url]
    public string $period = '12m';

    #[Url]
    public string $categoryFilter = '';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array<string, mixed>> */
    public array $allProducts = [];

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<string> */
    public array $months = [];

    /** @var array<string> */
    public array $categories = [];

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

            // Load product performance table with monthly breakdown
            $rawProducts = $bq->getProductPerformanceTable($brand->name, $this->period);

            // Store all products
            $this->allProducts = $rawProducts;

            // Extract months and categories from the data
            $this->months = [];
            $categorySet = [];
            foreach ($rawProducts as $product) {
                foreach (array_keys($product['months']) as $month) {
                    if (! in_array($month, $this->months)) {
                        $this->months[] = $month;
                    }
                }
                // Extract category
                $category = $product['category'] ?? '';
                if ($category !== '' && ! isset($categorySet[$category])) {
                    $categorySet[$category] = true;
                }
            }
            sort($this->months);
            $this->categories = array_keys($categorySet);
            sort($this->categories);

            // Apply category filter
            $this->applyFilters();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load product data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Apply filters to the product list.
     */
    protected function applyFilters(): void
    {
        if ($this->categoryFilter === '') {
            $this->products = $this->allProducts;

            return;
        }

        $this->products = array_filter($this->allProducts, function (array $product) {
            $category = $product['category'] ?? '';

            // Check if category matches (supports both exact match and partial match for subcategories)
            return $category === $this->categoryFilter || str_starts_with($category, $this->categoryFilter.'/');
        });

        // Re-index array
        $this->products = array_values($this->products);
    }

    public function updatedBrandId(): void
    {
        $this->categoryFilter = ''; // Reset category filter when brand changes
        $this->loadData();
    }

    public function updatedPeriod(): void
    {
        $this->loadData();
    }

    public function updatedCategoryFilter(): void
    {
        $this->applyFilters();
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
     * Get category options for the filter.
     *
     * @return array<string, string>
     */
    public function getCategoryOptions(): array
    {
        $options = ['' => 'All Categories'];
        foreach ($this->categories as $category) {
            // Get the last part of the category path for display
            $parts = explode('/', $category);
            $displayName = end($parts);
            $options[$category] = $displayName;
        }

        return $options;
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
