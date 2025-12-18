<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Models\BrandCompetitor;
use App\Services\BigQueryService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class MarketShare extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Market Share';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.supply-panel.pages.market-share';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public string $period = '30d';

    public bool $loading = true;

    public ?string $error = null;

    public string $search = '';

    /** @var array<string, array{name: string, brand_share: float, competitor_shares: array<string, float>, children: array<string, array{name: string, brand_share: float, competitor_shares: array<string, float>}>}> */
    public array $categoryTree = [];

    /** @var array<string> */
    public array $expandedCategories = [];

    /** @var array<string> */
    public array $competitorLabels = ['Competitor A', 'Competitor B', 'Competitor C'];

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
                $this->error = 'No competitor brands have been configured for market share analysis.';
                $this->loading = false;

                return;
            }

            // Load market share data
            $rawData = $bq->getMarketShareByCategory($brand->name, $competitorBrandNames, $this->period);

            // Build hierarchical tree
            $this->categoryTree = $this->buildCategoryTree($rawData);

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load market share data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build hierarchical category tree from flat data.
     *
     * @param  array<int, array{category: string, subcategory: ?string, brand_share: float, competitor_shares: array<string, float>}>  $rawData
     * @return array<string, array{name: string, brand_share: float, competitor_shares: array<string, float>, children: array<string, array{name: string, brand_share: float, competitor_shares: array<string, float>}>}>
     */
    private function buildCategoryTree(array $rawData): array
    {
        $tree = [];

        foreach ($rawData as $item) {
            $category = $item['category'];
            $subcategory = $item['subcategory'];

            if (! isset($tree[$category])) {
                $tree[$category] = [
                    'name' => $category,
                    'brand_share' => 0,
                    'competitor_shares' => [],
                    'children' => [],
                ];
            }

            if ($subcategory === null || $subcategory === '') {
                // This is a top-level category total
                $tree[$category]['brand_share'] = $item['brand_share'];
                $tree[$category]['competitor_shares'] = $item['competitor_shares'];
            } else {
                // This is a subcategory
                $tree[$category]['children'][$subcategory] = [
                    'name' => $subcategory,
                    'brand_share' => $item['brand_share'],
                    'competitor_shares' => $item['competitor_shares'],
                ];
            }
        }

        // Calculate parent totals if they weren't provided
        foreach ($tree as $category => &$data) {
            if ($data['brand_share'] === 0.0 && ! empty($data['children'])) {
                $totalBrandShare = 0;
                $competitorTotals = [];

                foreach ($data['children'] as $child) {
                    $totalBrandShare += $child['brand_share'];
                    foreach ($child['competitor_shares'] as $comp => $share) {
                        if (! isset($competitorTotals[$comp])) {
                            $competitorTotals[$comp] = 0;
                        }
                        $competitorTotals[$comp] += $share;
                    }
                }

                $childCount = count($data['children']);
                $data['brand_share'] = round($totalBrandShare / $childCount, 1);
                foreach ($competitorTotals as $comp => $total) {
                    $data['competitor_shares'][$comp] = round($total / $childCount, 1);
                }
            }
        }

        // Sort by brand share descending
        uasort($tree, fn ($a, $b) => $b['brand_share'] <=> $a['brand_share']);

        return $tree;
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
     * Toggle category expansion state.
     */
    public function toggleCategory(string $category): void
    {
        if (in_array($category, $this->expandedCategories)) {
            $this->expandedCategories = array_values(array_diff($this->expandedCategories, [$category]));
        } else {
            $this->expandedCategories[] = $category;
        }
    }

    /**
     * Expand all categories.
     */
    public function expandAll(): void
    {
        $this->expandedCategories = array_keys($this->categoryTree);
    }

    /**
     * Collapse all categories.
     */
    public function collapseAll(): void
    {
        $this->expandedCategories = [];
    }

    /**
     * Check if a category is expanded.
     */
    public function isExpanded(string $category): bool
    {
        return in_array($category, $this->expandedCategories);
    }

    /**
     * Get filtered category tree based on search.
     *
     * @return array<string, array{name: string, brand_share: float, competitor_shares: array<string, float>, children: array<string, array{name: string, brand_share: float, competitor_shares: array<string, float>}>}>
     */
    public function getFilteredTree(): array
    {
        if (empty($this->search)) {
            return $this->categoryTree;
        }

        $searchLower = strtolower($this->search);
        $filtered = [];

        foreach ($this->categoryTree as $category => $data) {
            $categoryMatches = str_contains(strtolower($category), $searchLower);

            // Check if any children match
            $matchingChildren = [];
            foreach ($data['children'] as $subcategory => $childData) {
                if (str_contains(strtolower($subcategory), $searchLower)) {
                    $matchingChildren[$subcategory] = $childData;
                }
            }

            $hasMatchingChildren = count($matchingChildren) > 0;

            if ($categoryMatches || $hasMatchingChildren) {
                $filtered[$category] = $data;
                if (! $categoryMatches) {
                    // Only show matching children if parent doesn't match
                    // (we know $hasMatchingChildren is true here since we're in the OR block)
                    $filtered[$category]['children'] = $matchingChildren;
                }
                // Auto-expand categories with matching children
                if ($hasMatchingChildren && ! in_array($category, $this->expandedCategories)) {
                    $this->expandedCategories[] = $category;
                }
            }
        }

        return $filtered;
    }

    /**
     * Get share bar color class based on percentage.
     */
    public function getShareColorClass(float $share, bool $isYourBrand = false): string
    {
        if ($isYourBrand) {
            return 'bg-primary-500';
        }

        if ($share >= 30) {
            return 'bg-red-400';
        } elseif ($share >= 20) {
            return 'bg-yellow-400';
        }

        return 'bg-gray-300';
    }

    public function updatedBrandId(): void
    {
        $this->loadData();
    }

    public function updatedPeriod(): void
    {
        $this->loadData();
    }

    public function updatedSearch(): void
    {
        // Re-filter is automatic via getFilteredTree()
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
