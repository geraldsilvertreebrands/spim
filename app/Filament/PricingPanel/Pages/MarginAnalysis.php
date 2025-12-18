<?php

namespace App\Filament\PricingPanel\Pages;

use App\Models\Entity;
use App\Models\PriceScrape;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MarginAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament.pricing-panel.pages.margin-analysis';

    protected static ?string $navigationLabel = 'Margin Analysis';

    protected static ?string $title = 'Margin Analysis';

    protected static ?int $navigationSort = 5;

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public ?string $categoryFilter = null;

    /**
     * Get margin analysis data for all products.
     */
    public function getMarginData(): Collection
    {
        // Get all entities (in production, you might want to filter by entity_type)
        $products = Entity::query()->get();

        $marginData = $products->map(function (Entity $product) {
            $name = $product->getAttr('title') ?? $product->entity_id;
            $ourPrice = (float) ($product->getAttr('price') ?? 0);
            $cost = (float) ($product->getAttr('cost') ?? 0);

            // Skip products without price or cost
            if ($ourPrice <= 0 || $cost <= 0) {
                return null;
            }

            // Calculate our margin
            $marginAmount = $ourPrice - $cost;
            $marginPercent = ($marginAmount / $ourPrice) * 100;

            // Get competitor prices
            $competitorPrices = PriceScrape::getLatestCompetitorPrices($product->id);

            // Calculate what our margin would be at competitor prices
            $competitorMargins = $competitorPrices->map(function ($scrape) use ($cost, $ourPrice) {
                $competitorPrice = (float) $scrape->price;
                $marginAtCompetitorPrice = $competitorPrice - $cost;
                $marginPercentAtCompetitorPrice = $competitorPrice > 0
                    ? ($marginAtCompetitorPrice / $competitorPrice) * 100
                    : 0;

                return [
                    'competitor' => $scrape->competitor_name,
                    'competitor_price' => $competitorPrice,
                    'margin_if_matched' => $marginAtCompetitorPrice,
                    'margin_percent_if_matched' => $marginPercentAtCompetitorPrice,
                    'price_difference' => $competitorPrice - $ourPrice,
                ];
            })->values();

            return [
                'product_id' => $product->id,
                'product_name' => $name,
                'our_price' => $ourPrice,
                'cost' => $cost,
                'margin_amount' => $marginAmount,
                'margin_percent' => $marginPercent,
                'competitor_margins' => $competitorMargins,
                'avg_competitor_price' => $competitorPrices->avg('price') ?? 0,
                'category' => $product->getAttr('category') ?? 'Uncategorized',
            ];
        })->filter(); // Remove nulls

        // Apply category filter
        if ($this->categoryFilter) {
            $marginData = $marginData->filter(fn ($item) => $item['category'] === $this->categoryFilter);
        }

        // Apply sorting
        $marginData = $this->sortMarginData($marginData);

        return $marginData->values();
    }

    /**
     * Sort margin data based on current sort settings.
     */
    protected function sortMarginData(Collection $data): Collection
    {
        $sortBy = $this->sortBy;
        $direction = $this->sortDirection === 'asc' ? 1 : -1;

        return $data->sort(function ($a, $b) use ($sortBy, $direction) {
            $aVal = match ($sortBy) {
                'name' => $a['product_name'],
                'price' => $a['our_price'],
                'cost' => $a['cost'],
                'margin_amount' => $a['margin_amount'],
                'margin_percent' => $a['margin_percent'],
                default => $a['product_name'],
            };

            $bVal = match ($sortBy) {
                'name' => $b['product_name'],
                'price' => $b['our_price'],
                'cost' => $b['cost'],
                'margin_amount' => $b['margin_amount'],
                'margin_percent' => $b['margin_percent'],
                default => $b['product_name'],
            };

            if (is_string($aVal) && is_string($bVal)) {
                return strcasecmp($aVal, $bVal) * $direction;
            }

            return ($aVal <=> $bVal) * $direction;
        });
    }

    /**
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $data = $this->getMarginData();

        if ($data->isEmpty()) {
            return [
                'total_products' => 0,
                'avg_margin_percent' => 0,
                'total_margin_amount' => 0,
                'lowest_margin_percent' => 0,
                'highest_margin_percent' => 0,
            ];
        }

        return [
            'total_products' => $data->count(),
            'avg_margin_percent' => round($data->avg('margin_percent'), 2),
            'total_margin_amount' => round($data->sum('margin_amount'), 2),
            'lowest_margin_percent' => round($data->min('margin_percent'), 2),
            'highest_margin_percent' => round($data->max('margin_percent'), 2),
        ];
    }

    /**
     * Get all unique categories from products.
     */
    public function getCategories(): array
    {
        return $this->getMarginData()
            ->pluck('category')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Update sort settings.
     */
    public function updateSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Set category filter.
     */
    public function filterByCategory(?string $category): void
    {
        $this->categoryFilter = $category;
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->categoryFilter = null;
    }

    /**
     * Refresh the page data.
     */
    public function refresh(): void
    {
        // Page will automatically re-render with fresh data
    }

    /**
     * Format currency value.
     */
    public function formatCurrency(float $amount): string
    {
        return 'R'.number_format($amount, 2);
    }

    /**
     * Format percentage value.
     */
    public function formatPercent(float $percent): string
    {
        return number_format($percent, 1).'%';
    }

    /**
     * Get color class for margin percentage.
     * Green for good margins (>30%), yellow for medium (15-30%), red for low (<15%).
     */
    public function getMarginColorClass(float $marginPercent): string
    {
        if ($marginPercent >= 30) {
            return 'text-green-600 bg-green-50';
        }

        if ($marginPercent >= 15) {
            return 'text-yellow-600 bg-yellow-50';
        }

        return 'text-red-600 bg-red-50';
    }

    /**
     * Get color class for margin difference.
     * Green if competitor price gives better margin, red if worse.
     */
    public function getCompetitorMarginColorClass(float $ourMargin, float $competitorMargin): string
    {
        $difference = $competitorMargin - $ourMargin;

        if ($difference > 5) {
            return 'text-green-600 bg-green-50';
        }

        if ($difference < -5) {
            return 'text-red-600 bg-red-50';
        }

        return 'text-gray-600 bg-gray-50';
    }
}
