<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Models\BrandCompetitor;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Benchmarks extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Benchmarks';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.supply-panel.pages.benchmarks';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $period = '30d';

    public bool $loading = true;

    public ?string $error = null;

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

        $colors = ['#006654', '#3B82F6', '#F59E0B', '#EF4444'];

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
}
