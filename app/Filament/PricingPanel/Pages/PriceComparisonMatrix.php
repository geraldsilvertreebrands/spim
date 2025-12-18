<?php

namespace App\Filament\PricingPanel\Pages;

use App\Models\Entity;
use App\Models\PriceScrape;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PriceComparisonMatrix extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Price Matrix';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pricing-panel.pages.price-comparison-matrix';

    /** @var array<int, array<string, mixed>> */
    public array $matrixData = [];

    /** @var array<int, string> */
    public array $competitors = [];

    public string $selectedCategory = 'all';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $loading = true;

    public ?string $error = null;

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            // Get all distinct competitors
            $this->competitors = PriceScrape::getCompetitors()->toArray();

            // Build matrix data
            $this->matrixData = $this->buildMatrixData();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load price comparison matrix: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build price comparison matrix data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildMatrixData(): array
    {
        // Get all products that have price scrapes
        $productIds = PriceScrape::select('product_id')
            ->distinct()
            ->pluck('product_id');

        $products = Entity::whereIn('id', $productIds)
            ->get();

        $matrixData = [];

        foreach ($products as $product) {
            // Get our price (from product attributes)
            $ourPrice = $this->getProductPrice($product);

            // Skip if no our price
            if ($ourPrice === null) {
                continue;
            }

            // Get latest competitor prices
            $competitorPrices = PriceScrape::getLatestCompetitorPrices($product->id);

            // Build competitor price array
            $prices = [];
            foreach ($this->competitors as $competitor) {
                $scrape = $competitorPrices->firstWhere('competitor_name', $competitor);
                $prices[$competitor] = $scrape ? (float) $scrape->price : null;
            }

            // Calculate price position for color coding
            $position = $this->calculatePricePosition($ourPrice, $competitorPrices);

            $matrixData[] = [
                'id' => $product->id,
                'name' => $product->name ?? $product->getAttr('name') ?? 'Unknown Product',
                'category' => $product->getAttr('category') ?? 'Uncategorized',
                'our_price' => $ourPrice,
                'competitor_prices' => $prices,
                'position' => $position,
            ];
        }

        // Apply sorting
        $matrixData = $this->applySorting($matrixData);

        // Apply filtering
        if ($this->selectedCategory !== 'all') {
            $matrixData = array_filter($matrixData, function ($item) {
                return $item['category'] === $this->selectedCategory;
            });
        }

        return array_values($matrixData);
    }

    /**
     * Get the price for a product entity.
     */
    protected function getProductPrice(Entity $product): ?float
    {
        $priceAttrs = ['price', 'selling_price', 'retail_price', 'base_price'];

        foreach ($priceAttrs as $attr) {
            $price = $product->getAttr($attr);
            if ($price !== null) {
                return (float) $price;
            }
        }

        return null;
    }

    /**
     * Calculate price position (cheapest, middle, most expensive).
     *
     * @param  \Illuminate\Support\Collection<int, PriceScrape>  $competitorPrices
     */
    protected function calculatePricePosition(float $ourPrice, Collection $competitorPrices): string
    {
        if ($competitorPrices->isEmpty()) {
            return 'unknown';
        }

        $allPrices = $competitorPrices->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $allPrices[] = $ourPrice;

        $minPrice = min($allPrices);
        $maxPrice = max($allPrices);

        if ($ourPrice == $minPrice) {
            return 'cheapest';
        }

        if ($ourPrice == $maxPrice) {
            return 'most_expensive';
        }

        return 'middle';
    }

    /**
     * Apply sorting to matrix data.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function applySorting(array $data): array
    {
        $sortField = $this->sortBy;
        $direction = $this->sortDirection === 'asc' ? 1 : -1;

        usort($data, function ($a, $b) use ($sortField, $direction) {
            $aVal = $a[$sortField] ?? null;
            $bVal = $b[$sortField] ?? null;

            if ($aVal === $bVal) {
                return 0;
            }

            if ($aVal === null) {
                return 1;
            }
            if ($bVal === null) {
                return -1;
            }

            return ($aVal <=> $bVal) * $direction;
        });

        return $data;
    }

    /**
     * Get all unique categories from products.
     *
     * @return array<string, string>
     */
    public function getCategories(): array
    {
        $productIds = PriceScrape::select('product_id')
            ->distinct()
            ->pluck('product_id');

        $categories = Entity::whereIn('id', $productIds)
            ->get()
            ->map(fn ($p) => $p->getAttr('category') ?? 'Uncategorized')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return array_merge(['all' => 'All Categories'], array_combine($categories, $categories));
    }

    /**
     * Update category filter.
     */
    public function updateCategory(string $category): void
    {
        $this->selectedCategory = $category;
        $this->loadData();
    }

    /**
     * Update sorting.
     */
    public function updateSort(string $field, string $direction = 'asc'): void
    {
        $this->sortBy = $field;
        $this->sortDirection = $direction;
        $this->loadData();
    }

    /**
     * Refresh data.
     */
    public function refresh(): void
    {
        $this->loadData();
    }

    /**
     * Get cell background color class based on price position.
     */
    public function getCellColorClass(float $ourPrice, ?float $competitorPrice): string
    {
        if ($competitorPrice === null) {
            return 'bg-gray-50 dark:bg-gray-800';
        }

        // Compare our price to this specific competitor
        if ($ourPrice < $competitorPrice) {
            // We're cheaper - green
            return 'bg-green-50 dark:bg-green-900/20';
        } elseif ($ourPrice > $competitorPrice) {
            // We're more expensive - red
            return 'bg-red-50 dark:bg-red-900/20';
        }

        // Same price - yellow
        return 'bg-yellow-50 dark:bg-yellow-900/20';
    }

    /**
     * Get our price cell background color based on overall position.
     */
    public function getOurPriceCellColorClass(string $position): string
    {
        return match ($position) {
            'cheapest' => 'bg-green-100 dark:bg-green-900/30 font-bold',
            'middle' => 'bg-yellow-100 dark:bg-yellow-900/30',
            'most_expensive' => 'bg-red-100 dark:bg-red-900/30',
            default => 'bg-gray-100 dark:bg-gray-800',
        };
    }

    /**
     * Format price for display.
     */
    public function formatPrice(?float $price): string
    {
        if ($price === null) {
            return '-';
        }

        return 'R'.number_format($price, 2);
    }

    /**
     * Get price difference for tooltip.
     */
    public function getPriceDifference(float $ourPrice, ?float $competitorPrice): string
    {
        if ($competitorPrice === null) {
            return 'No data';
        }

        $diff = $ourPrice - $competitorPrice;

        if ($diff > 0) {
            return '+R'.number_format($diff, 2).' (more expensive)';
        } elseif ($diff < 0) {
            return 'R'.number_format($diff, 2).' (cheaper)';
        }

        return 'Same price';
    }
}
