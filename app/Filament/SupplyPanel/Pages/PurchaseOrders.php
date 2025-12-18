<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class PurchaseOrders extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Purchase Orders';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.supply-panel.pages.purchase-orders';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $period = '12m';

    public bool $loading = true;

    public ?string $error = null;

    /** @var array{total_pos: int, on_time_pct: float, in_full_pct: float, otif_pct: float} */
    public array $summary = [
        'total_pos' => 0,
        'on_time_pct' => 0,
        'in_full_pct' => 0,
        'otif_pct' => 0,
    ];

    /** @var array{labels: array<string>, datasets: array<array{label: string, data: array<mixed>, type?: string, yAxisID?: string, borderColor?: string, backgroundColor?: string}>} */
    public array $chartData = ['labels' => [], 'datasets' => []];

    /** @var array<int, array<string, mixed>> */
    public array $orders = [];

    // Modal state
    public bool $showDetailModal = false;

    public ?string $selectedPoNumber = null;

    /** @var array<int, array<string, mixed>> */
    public array $selectedPoLines = [];

    /** @var array<string, mixed>|null */
    public ?array $selectedPoDetails = null;

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

            // Load purchase order data from BigQuery
            $poData = $bq->getPurchaseOrders($brand->name, $months);

            $this->summary = $poData['summary'];
            $this->orders = $poData['orders'];

            // Build chart data
            $this->chartData = $this->buildChartData($poData['monthly']);

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load purchase order data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build chart data from monthly PO data.
     *
     * @param  array<int, array{month: string, po_count: int, on_time_pct: float, in_full_pct: float, otif_pct: float}>  $monthlyData
     * @return array{labels: array<string>, datasets: array<array{label: string, data: array<mixed>, type?: string, yAxisID?: string, borderColor?: string, backgroundColor?: string}>}
     */
    private function buildChartData(array $monthlyData): array
    {
        $labels = array_column($monthlyData, 'month');
        $poCounts = array_column($monthlyData, 'po_count');
        $onTimePcts = array_column($monthlyData, 'on_time_pct');
        $inFullPcts = array_column($monthlyData, 'in_full_pct');

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'PO Count',
                    'data' => $poCounts,
                    'type' => 'bar',
                    'yAxisID' => 'y',
                    'backgroundColor' => 'rgba(0, 102, 84, 0.6)',
                    'borderColor' => '#006654',
                ],
                [
                    'label' => '% On-Time',
                    'data' => $onTimePcts,
                    'type' => 'line',
                    'yAxisID' => 'y1',
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => '% In-Full',
                    'data' => $inFullPcts,
                    'type' => 'line',
                    'yAxisID' => 'y1',
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
            ],
        ];
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
     * Open detail modal for a specific PO.
     */
    public function openPoDetail(string $poNumber): void
    {
        $this->selectedPoNumber = $poNumber;

        // Find the PO in the orders array
        foreach ($this->orders as $order) {
            if ($order['po_number'] === $poNumber) {
                $this->selectedPoDetails = $order;
                break;
            }
        }

        // Load PO lines from BigQuery
        try {
            $bq = app(BigQueryService::class);
            $this->selectedPoLines = $bq->getPurchaseOrderLines($poNumber);
        } catch (\Exception $e) {
            $this->selectedPoLines = [];
        }

        $this->showDetailModal = true;
    }

    /**
     * Close the detail modal.
     */
    public function closePoDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedPoNumber = null;
        $this->selectedPoLines = [];
        $this->selectedPoDetails = null;
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
     * Get status badge class.
     */
    public function getStatusBadgeClass(string $status): string
    {
        return match (strtolower($status)) {
            'delivered' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'partial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            'pending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
        };
    }

    /**
     * Export orders to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'purchase_orders_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Headers
            fputcsv($handle, [
                'PO Number',
                'Order Date',
                'Expected Delivery',
                'Actual Delivery',
                'Status',
                'Lines',
                'Total Value',
                'On-Time',
                'In-Full',
            ]);

            // Data rows
            foreach ($this->orders as $order) {
                fputcsv($handle, [
                    $order['po_number'] ?? '',
                    $order['order_date'] ?? '',
                    $order['expected_delivery_date'] ?? '',
                    $order['actual_delivery_date'] ?? '',
                    $order['status'] ?? '',
                    $order['line_count'] ?? 0,
                    $order['total_value'] ?? 0,
                    ($order['delivered_on_time'] ?? false) ? 'Yes' : 'No',
                    ($order['delivered_in_full'] ?? false) ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename);
    }
}
