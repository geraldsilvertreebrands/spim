<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Filament\SupplyPanel\Concerns\HasBrandContext;
use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class SupplyChain extends Page
{
    use HasBrandContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Supply Chain';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.supply-panel.pages.supply-chain';

    #[Url]
    public string $period = '12m';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array<string, mixed>> */
    public array $sellInData = [];

    /** @var array<int, array<string, mixed>> */
    public array $sellOutData = [];

    /** @var array<int, array<string, mixed>> */
    public array $closingStockData = [];

    /** @var array<string> */
    public array $months = [];

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

            $months = $this->periodToMonths($this->period);

            // Load stock supply data from BigQuery
            $stockData = $bq->getStockSupply($brand->name, $months);

            $this->sellInData = $stockData['sell_in'];
            $this->sellOutData = $stockData['sell_out'];
            $this->closingStockData = $stockData['closing_stock'];

            // Extract months from ALL data sources (sell_in, sell_out, closing_stock)
            $this->months = [];
            $allData = array_merge($this->sellInData, $this->sellOutData, $this->closingStockData);
            foreach ($allData as $product) {
                foreach (array_keys($product['months'] ?? []) as $month) {
                    if (! in_array($month, $this->months)) {
                        $this->months[] = $month;
                    }
                }
            }
            sort($this->months);

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load supply chain data: '.$e->getMessage();
            $this->loading = false;
        }
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
     * Calculate MoM change for a value.
     *
     * @param  array<string, int>  $months
     */
    public function calculateMomChange(array $months, string $currentMonth): ?float
    {
        $sortedMonths = array_keys($months);
        sort($sortedMonths);

        $currentIndex = array_search($currentMonth, $sortedMonths);
        if ($currentIndex === false || $currentIndex === 0) {
            return null;
        }

        $prevMonth = $sortedMonths[$currentIndex - 1];
        $currentValue = $months[$currentMonth] ?? 0;
        $prevValue = $months[$prevMonth] ?? 0;

        if ($prevValue === 0) {
            return null;
        }

        return round((($currentValue - $prevValue) / $prevValue) * 100, 1);
    }

    /**
     * Export sell-in data to CSV.
     */
    public function exportSellInToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->exportTableToCsv($this->sellInData, 'sell_in');
    }

    /**
     * Export sell-out data to CSV.
     */
    public function exportSellOutToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->exportTableToCsv($this->sellOutData, 'sell_out');
    }

    /**
     * Export closing stock data to CSV.
     */
    public function exportClosingStockToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->exportTableToCsv($this->closingStockData, 'closing_stock');
    }

    /**
     * Export table data to CSV.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function exportTableToCsv(array $data, string $type): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = "{$type}_".date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Headers
            $headers = ['SKU', 'Name'];
            foreach ($this->months as $month) {
                $headers[] = $month;
            }
            $headers[] = 'Total';
            fputcsv($handle, $headers);

            // Data rows
            foreach ($data as $product) {
                $row = [
                    $product['sku'] ?? '',
                    $product['name'] ?? '',
                ];
                $total = 0;
                foreach ($this->months as $month) {
                    $value = $product['months'][$month] ?? 0;
                    $row[] = $value;
                    $total += $value;
                }
                $row[] = $total;
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename);
    }
}
