<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use App\Services\CompanyService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class SubscriptionsOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static string|\UnitEnum|null $navigationGroup = 'Pet Heaven';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.supply-panel.pages.subscriptions-overview';

    #[Url]
    public ?int $brandId = null;

    #[Url]
    public int $monthsBack = 12;

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<string, mixed> */
    public array $summary = [];

    /** @var array<int, array<string, mixed>> */
    public array $monthlyTrend = [];

    /** @var array<int, array<string, mixed>> */
    public array $byFrequency = [];

    /** @var array<string, mixed> */
    public array $chartData = [];

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

        // Default to user's first Pet Heaven brand if not specified
        if (! $this->brandId) {
            $petHeavenBrands = $this->getPetHeavenBrands();
            $this->brandId = array_key_first($petHeavenBrands);
        }

        // Verify user can access this brand and it's Pet Heaven
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

            // Load subscription overview data
            $data = $bq->getSubscriptionOverview($brand->name, $this->monthsBack);

            $this->summary = $data['summary'];
            $this->monthlyTrend = $data['monthly'];
            $this->byFrequency = $data['by_frequency'];

            // Build chart data
            $this->chartData = $this->buildChartData();

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load subscription data: '.$e->getMessage();
            $this->loading = false;
        }
    }

    /**
     * Build chart data for visualization.
     *
     * @return array<string, mixed>
     */
    private function buildChartData(): array
    {
        $labels = array_column($this->monthlyTrend, 'month');
        $newSubscriptions = array_column($this->monthlyTrend, 'new_subscriptions');
        $churned = array_column($this->monthlyTrend, 'churned');
        $netChange = array_column($this->monthlyTrend, 'net_change');

        return [
            'subscriptions' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'New Subscriptions',
                        'data' => $newSubscriptions,
                        'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                        'borderColor' => '#10B981',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => 'Churned',
                        'data' => $churned,
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                        'borderColor' => '#EF4444',
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'netChange' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Net Change',
                        'data' => $netChange,
                        'backgroundColor' => array_map(fn ($v) => $v >= 0 ? 'rgba(16, 185, 129, 0.8)' : 'rgba(239, 68, 68, 0.8)', $netChange),
                        'borderColor' => array_map(fn ($v) => $v >= 0 ? '#10B981' : '#EF4444', $netChange),
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'frequency' => [
                'labels' => array_column($this->byFrequency, 'frequency'),
                'datasets' => [
                    [
                        'label' => 'Subscriptions',
                        'data' => array_column($this->byFrequency, 'count'),
                        'backgroundColor' => [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(236, 72, 153, 0.8)',
                        ],
                    ],
                ],
            ],
        ];
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
     * Format number with K/M suffix.
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
     * Get retention rate color class.
     */
    public function getRetentionColorClass(float $rate): string
    {
        if ($rate >= 90) {
            return 'text-green-600 dark:text-green-400';
        }
        if ($rate >= 80) {
            return 'text-emerald-600 dark:text-emerald-400';
        }
        if ($rate >= 70) {
            return 'text-yellow-600 dark:text-yellow-400';
        }

        return 'text-red-600 dark:text-red-400';
    }

    /**
     * Check if has subscription data.
     */
    public function hasSubscriptionData(): bool
    {
        return ! empty($this->summary);
    }
}
