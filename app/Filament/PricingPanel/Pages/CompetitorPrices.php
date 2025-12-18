<?php

namespace App\Filament\PricingPanel\Pages;

use App\Models\Entity;
use App\Models\PriceScrape;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class CompetitorPrices extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Competitor Prices';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pricing-panel.pages.competitor-prices';

    public array $productPrices = [];

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

            // Get products with pricing data
            $this->productPrices = $this->buildProductPriceData();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load competitor prices: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build product price comparison data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildProductPriceData(): array
    {
        // Get all products that have price scrapes
        $productIds = PriceScrape::select('product_id')
            ->distinct()
            ->pluck('product_id');

        $products = Entity::whereIn('id', $productIds)
            ->get();

        $productData = [];

        foreach ($products as $product) {
            // Get our price (from product attributes)
            $ourPrice = $this->getOurPrice($product);

            // Skip if no our price
            if ($ourPrice === null) {
                continue;
            }

            // Get latest competitor prices
            $competitorPrices = PriceScrape::getLatestCompetitorPrices($product->id);

            // Build competitor price array
            $prices = [
                'our_price' => $ourPrice,
            ];

            foreach ($this->competitors as $competitor) {
                $scrape = $competitorPrices->firstWhere('competitor_name', $competitor);
                $prices[$competitor] = $scrape ? (float) $scrape->price : null;
            }

            // Calculate price position
            $position = $this->calculatePricePosition($ourPrice, $competitorPrices);

            // Calculate price difference from cheapest competitor
            $priceDifference = $this->calculatePriceDifference($ourPrice, $competitorPrices);

            $productData[] = [
                'id' => $product->id,
                'name' => $product->name ?? $product->getAttr('name') ?? 'Unknown Product',
                'category' => $product->getAttr('category') ?? 'Uncategorized',
                'our_price' => $ourPrice,
                'competitor_prices' => $prices,
                'position' => $position,
                'price_difference' => $priceDifference,
                'is_more_expensive' => $priceDifference > 0,
            ];
        }

        // Apply sorting
        $productData = $this->applySorting($productData);

        // Apply filtering
        if ($this->selectedCategory !== 'all') {
            $productData = array_filter($productData, function ($item) {
                return $item['category'] === $this->selectedCategory;
            });
        }

        return array_values($productData);
    }

    /**
     * Get our price for the product.
     */
    protected function getOurPrice(Entity $product): ?float
    {
        // Try to get price from product attributes
        $price = $product->getAttr('price');

        if ($price !== null) {
            return (float) $price;
        }

        // Fallback: try other common price attributes
        $priceAttrs = ['selling_price', 'retail_price', 'base_price'];
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
     * Calculate price difference from cheapest competitor.
     * Positive = we're more expensive, Negative = we're cheaper.
     *
     * @param  \Illuminate\Support\Collection<int, PriceScrape>  $competitorPrices
     */
    protected function calculatePriceDifference(float $ourPrice, Collection $competitorPrices): float
    {
        if ($competitorPrices->isEmpty()) {
            return 0.0;
        }

        $lowestCompetitorPrice = $competitorPrices->min(fn ($scrape) => (float) $scrape->price);

        return round($ourPrice - $lowestCompetitorPrice, 2);
    }

    /**
     * Apply sorting to product data.
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
     * @return array<int, string>
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
     * Get position badge class for styling.
     */
    public function getPositionBadgeClass(string $position): string
    {
        return match ($position) {
            'cheapest' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'middle' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'most_expensive' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
        };
    }

    /**
     * Get position label for display.
     */
    public function getPositionLabel(string $position): string
    {
        return match ($position) {
            'cheapest' => 'Cheapest',
            'middle' => 'Mid-Range',
            'most_expensive' => 'Most Expensive',
            default => 'Unknown',
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
}
