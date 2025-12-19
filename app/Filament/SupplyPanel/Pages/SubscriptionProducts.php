<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use App\Services\CompanyService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class SubscriptionProducts extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Subscription Products';

    protected static string|\UnitEnum|null $navigationGroup = 'Pet Heaven';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.supply-panel.pages.subscription-products';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public int $monthsBack = 12;

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<string, mixed> */
    public array $totals = [];

    public string $sortColumn = 'active_subscriptions';

    public string $sortDirection = 'desc';

    /**
     * Determine if this page should be shown in navigation.
     * Only visible for Pet Heaven deployments with Premium users.
     */
    public static function shouldRegisterNavigation(): bool
    {
        // Only show in Pet Heaven deployments
        if (! CompanyService::isPetHeaven()) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Admin can always see (in PH deployment)
        if ($user->hasRole('admin')) {
            return true;
        }

        // Must be premium
        if (! $user->hasRole('supplier-premium')) {
            return false;
        }

        // Must have access to a Pet Heaven brand
        $brandIds = $user->accessibleBrandIds();
        $hasPetHeavenBrand = Brand::whereIn('id', $brandIds)
            ->where('company_id', CompanyService::COMPANY_PET_HEAVEN)
            ->exists();

        return $hasPetHeavenBrand;
    }

    public function mount(): void
    {
        // Block access if not a Pet Heaven deployment
        if (! CompanyService::isPetHeaven()) {
            abort(403, 'This feature is only available for Pet Heaven.');
        }

        // Default to user's first Pet Heaven brand
        if (! $this->brandId) {
            $petHeavenBrands = $this->getPetHeavenBrands();
            $this->brandId = array_key_first($petHeavenBrands);
        }

        // Verify access
        if ($this->brandId) {
            $brand = Brand::find($this->brandId);
            if (! $brand || ! auth()->user()->canAccessBrand($brand)) {
                $this->error = 'You do not have access to this brand.';
                $this->loading = false;

                return;
            }
            if (! $brand->isPetHeaven()) {
                $this->error = 'Subscription data is only available for Pet Heaven brands.';
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

            $this->products = $bq->getSubscriptionProducts($brand->name, $this->monthsBack);

            // Calculate totals
            $this->totals = [
                'total_subscriptions' => array_sum(array_column($this->products, 'total_subscriptions')),
                'active_subscriptions' => array_sum(array_column($this->products, 'active_subscriptions')),
                'mrr' => array_sum(array_column($this->products, 'mrr')),
                'subscribers' => array_sum(array_column($this->products, 'subscribers')),
            ];

            // Apply sorting
            $this->applySorting();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load subscription products: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Apply sorting to products array.
     */
    private function applySorting(): void
    {
        $column = $this->sortColumn;
        $direction = $this->sortDirection;

        usort($this->products, function ($a, $b) use ($column, $direction) {
            $aVal = $a[$column] ?? 0;
            $bVal = $b[$column] ?? 0;

            if ($direction === 'asc') {
                return $aVal <=> $bVal;
            }

            return $bVal <=> $aVal;
        });
    }

    /**
     * Sort by column.
     */
    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'desc';
        }

        $this->applySorting();
    }

    /**
     * Get sort icon for column.
     */
    public function getSortIcon(string $column): string
    {
        if ($this->sortColumn !== $column) {
            return '↕';
        }

        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    public function updatedBrandId(): void
    {
        $this->loadData();
    }

    public function updatedMonthsBack(): void
    {
        $this->loadData();
    }

    /**
     * Get Pet Heaven brands for the current user.
     *
     * @return array<int, string>
     */
    public function getPetHeavenBrands(): array
    {
        $user = auth()->user();
        $brandIds = $user->accessibleBrandIds();

        return Brand::whereIn('id', $brandIds)
            ->where('company_id', CompanyService::COMPANY_PET_HEAVEN)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get time period options.
     *
     * @return array<int, string>
     */
    public function getMonthsBackOptions(): array
    {
        return [
            3 => 'Last 3 Months',
            6 => 'Last 6 Months',
            12 => 'Last 12 Months',
            24 => 'Last 24 Months',
        ];
    }

    /**
     * Format currency value.
     */
    public function formatCurrency(float|int $value): string
    {
        if ($value >= 1000000) {
            return 'R'.number_format($value / 1000000, 1).'M';
        }
        if ($value >= 1000) {
            return 'R'.number_format($value / 1000, 1).'K';
        }

        return 'R'.number_format($value, 0);
    }

    /**
     * Format number.
     */
    public function formatNumber(float|int $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1).'M';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 1).'K';
        }

        return number_format($value, 0);
    }

    /**
     * Format percentage.
     */
    public function formatPercent(float $value): string
    {
        return number_format($value, 1).'%';
    }

    /**
     * Get churn rate color class.
     */
    public function getChurnColorClass(float $rate): string
    {
        if ($rate <= 5) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($rate <= 10) {
            return 'text-yellow-600 dark:text-yellow-400';
        }
        if ($rate <= 20) {
            return 'text-orange-600 dark:text-orange-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Check if has products.
     */
    public function hasProducts(): bool
    {
        return ! empty($this->products);
    }
}
