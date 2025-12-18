<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class CustomerEngagement extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Customer Engagement';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.supply-panel.pages.customer-engagement';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $period = '12m';

    #[Url]
    public string $sortColumn = 'sku';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array<string, mixed>> */
    public array $engagementData = [];

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

            // Load customer engagement data from BigQuery
            $this->engagementData = $bq->getCustomerEngagement($brand->name, $this->period);

            // Sort the data
            $this->sortData();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load customer engagement data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Sort the engagement data by the selected column.
     */
    private function sortData(): void
    {
        if (empty($this->engagementData)) {
            return;
        }

        $column = $this->sortColumn;
        $direction = $this->sortDirection;

        usort($this->engagementData, function ($a, $b) use ($column, $direction) {
            $aVal = $a[$column] ?? 0;
            $bVal = $b[$column] ?? 0;

            // Handle string vs numeric comparison
            if (is_string($aVal) && is_string($bVal)) {
                $result = strcasecmp($aVal, $bVal);
            } else {
                $result = $aVal <=> $bVal;
            }

            return $direction === 'asc' ? $result : -$result;
        });
    }

    /**
     * Sort by a column.
     */
    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            // Toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->sortData();
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
            '6m' => 'Last 6 Months',
            '12m' => 'Last 12 Months',
        ];
    }

    /**
     * Get metric definitions for tooltips.
     *
     * @return array<string, array{title: string, description: string}>
     */
    public function getMetricDefinitions(): array
    {
        return [
            'avg_qty_per_order' => [
                'title' => 'Avg Qty per Order',
                'description' => 'The average quantity of this product per order when customers purchase it. Higher values indicate customers tend to buy multiple units.',
            ],
            'reorder_rate' => [
                'title' => 'Reorder Rate',
                'description' => 'The percentage of customers who purchase this product again within 6 months of their first purchase. Higher rates indicate strong customer loyalty.',
            ],
            'avg_frequency_months' => [
                'title' => 'Avg Frequency',
                'description' => 'The average number of months between repeat purchases for customers who buy this product more than once. Lower values indicate more frequent repurchases.',
            ],
            'promo_intensity' => [
                'title' => 'Promo Intensity',
                'description' => 'The percentage of revenue generated from orders where this product was sold at a promotional discount. High values may indicate price sensitivity.',
            ],
        ];
    }

    /**
     * Get CSS class for sort icon.
     */
    public function getSortIconClass(string $column): string
    {
        if ($this->sortColumn !== $column) {
            return 'text-gray-400';
        }

        return 'text-primary-600 dark:text-primary-400';
    }

    /**
     * Get sort icon direction indicator.
     */
    public function getSortIcon(string $column): string
    {
        if ($this->sortColumn !== $column) {
            return '↕';
        }

        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    /**
     * Export engagement data to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'customer_engagement_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Headers
            fputcsv($handle, [
                'SKU',
                'Product Name',
                'Avg Qty per Order',
                'Reorder Rate (%)',
                'Avg Frequency (months)',
                'Promo Intensity (%)',
            ]);

            // Data rows
            foreach ($this->engagementData as $product) {
                fputcsv($handle, [
                    $product['sku'] ?? '',
                    $product['name'] ?? '',
                    $product['avg_qty_per_order'] ?? 0,
                    $product['reorder_rate'] ?? 0,
                    $product['avg_frequency_months'] ?? '',
                    $product['promo_intensity'] ?? 0,
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    /**
     * Format a metric value for display.
     */
    public function formatMetric(string $metric, mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        return match ($metric) {
            'avg_qty_per_order' => number_format((float) $value, 2),
            'reorder_rate', 'promo_intensity' => number_format((float) $value, 1).'%',
            'avg_frequency_months' => number_format((float) $value, 1).' mo',
            default => (string) $value,
        };
    }

    /**
     * Get color class for reorder rate.
     */
    public function getReorderRateColor(float $rate): string
    {
        if ($rate >= 30) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($rate >= 15) {
            return 'text-yellow-600 dark:text-yellow-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Get color class for promo intensity.
     */
    public function getPromoIntensityColor(float $intensity): string
    {
        if ($intensity <= 20) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($intensity <= 50) {
            return 'text-yellow-600 dark:text-yellow-400';
        }

        return 'text-red-600 dark:text-red-400';
    }
}
