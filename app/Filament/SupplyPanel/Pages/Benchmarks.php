<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Filament\SupplyPanel\Concerns\HasBrandContext;
use App\Models\Brand;
use App\Models\BrandCompetitor;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Benchmarks extends Page
{
    use HasBrandContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Benchmarks';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.supply-panel.pages.benchmarks';

    #[Url]
    public string $period = '30d';

    public bool $loading = true;

    public ?string $error = null;

    public bool $showTrendAsTable = false;

    /**
     * Toggle between chart and table view for trend comparison.
     */
    public function toggleTrendView(): void
    {
        $this->showTrendAsTable = ! $this->showTrendAsTable;

        // If switching back to chart view, dispatch event to re-initialize chart
        if (! $this->showTrendAsTable) {
            $this->dispatch('benchmarks-data-updated',
                revenueData: $this->revenueComparisonData,
                trendData: $this->trendComparisonData
            );
        }
    }

    /**
     * Get trend comparison data formatted for table display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTrendTableData(): array
    {
        if (empty($this->trendComparisonData['labels']) || empty($this->trendComparisonData['datasets'])) {
            return [];
        }

        $data = [];
        $labels = $this->trendComparisonData['labels'];
        $datasets = $this->trendComparisonData['datasets'];

        foreach ($labels as $index => $month) {
            $row = ['month' => $month];
            foreach ($datasets as $dataset) {
                $label = $dataset['label'] ?? 'Unknown';
                $row[$label] = $dataset['data'][$index] ?? 0;
            }
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Get dataset labels for table header.
     *
     * @return array<string>
     */
    public function getTrendDatasetLabels(): array
    {
        if (empty($this->trendComparisonData['datasets'])) {
            return [];
        }

        return array_map(fn ($ds) => $ds['label'] ?? 'Unknown', $this->trendComparisonData['datasets']);
    }

    /** @var array{labels: array<string>, datasets: array<array<string, mixed>>} */
    public array $revenueComparisonData = ['labels' => [], 'datasets' => []];

    /** @var array{labels: array<string>, datasets: array<array<string, mixed>>} */
    public array $trendComparisonData = ['labels' => [], 'datasets' => []];

    /** @var array<int, array{category: string, subcategory: ?string, brand_share: float, competitor_shares: array<string, float>}> */
    public array $marketShareData = [];

    /** @var array<string> */
    public array $competitorLabels = ['Competitor A', 'Competitor B', 'Competitor C'];

    /**
     * Return empty heading to hide the page header.
     */
    public function getHeading(): string
    {
        return '';
    }

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

            // Get competitor brands
            $competitorBrandNames = $this->getCompetitorBrandNames($brand);

            if (empty($competitorBrandNames)) {
                // No competitors configured - show message
                $this->error = 'No competitor brands have been configured for benchmarking.';
                $this->loading = false;

                return;
            }

            // Load revenue comparison (bar chart)
            $this->revenueComparisonData = $this->getRevenueComparison($bq, $brand->name, $competitorBrandNames);

            // Load trend comparison (line chart)
            $this->trendComparisonData = $bq->getCompetitorComparison($brand->name, $competitorBrandNames, $this->period);

            // Load market share by category
            $this->marketShareData = $bq->getMarketShareByCategory($brand->name, $competitorBrandNames, $this->period);

            $this->loading = false;

            // Dispatch event to update charts in JavaScript
            $this->dispatch('benchmarks-data-updated',
                revenueData: $this->revenueComparisonData,
                trendData: $this->trendComparisonData
            );
        } catch (\Exception $e) {
            $this->error = 'Failed to load benchmark data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Get competitor brand names for the given brand.
     *
     * @return array<string>
     */
    private function getCompetitorBrandNames(Brand $brand): array
    {
        $competitors = BrandCompetitor::where('brand_id', $brand->id)
            ->orderBy('position')
            ->with('competitor')
            ->get();

        $names = [];
        foreach ($competitors as $competitor) {
            /** @var Brand|null $competitorBrand */
            $competitorBrand = $competitor->competitor;
            if ($competitorBrand !== null) {
                $names[] = $competitorBrand->name;
            }
        }

        return $names;
    }

    /**
     * Get revenue comparison data for bar chart.
     *
     * @param  array<string>  $competitorBrands
     * @return array{labels: array<string>, datasets: array<array<string, mixed>>}
     */
    private function getRevenueComparison(BigQueryService $bq, string $brandName, array $competitorBrands): array
    {
        $allBrands = array_merge([$brandName], $competitorBrands);

        // Get KPIs for each brand
        $revenues = [];
        $labels = ['Your Brand'];

        foreach ($this->competitorLabels as $index => $label) {
            if (isset($competitorBrands[$index])) {
                $labels[] = $label;
            }
        }

        foreach ($allBrands as $brand) {
            try {
                $kpis = $bq->getBrandKpis($brand, $this->period);
                $revenues[] = $kpis['revenue'];
            } catch (\Exception $e) {
                $revenues[] = 0.0;
            }
        }

        $competitorColors = config('charts.competitors', [
            'your_brand' => '#264653',
            'competitor_a' => '#2a9d8f',
            'competitor_b' => '#e9c46a',
            'competitor_c' => '#e36040',
        ]);
        $colors = [
            $competitorColors['your_brand'],
            $competitorColors['competitor_a'],
            $competitorColors['competitor_b'],
            $competitorColors['competitor_c'],
        ];

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenues,
                    'backgroundColor' => array_slice($colors, 0, count($revenues)),
                ],
            ],
        ];
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
}
