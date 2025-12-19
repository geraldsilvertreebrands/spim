<?php

namespace App\Filament\SupplyPanel\Pages;

use App\Models\Brand;
use App\Services\BigQueryService;
use App\Services\CompanyService;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class SubscriptionPredictions extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Predictions';

    protected static string|\UnitEnum|null $navigationGroup = 'Pet Heaven';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.supply-panel.pages.subscription-predictions';

    #[Url]
    public ?int $brandId = null;

    public bool $loading = true;

    public ?string $error = null;

    /** @var array<int, array<string, mixed>> */
    public array $upcoming = [];

    /** @var array<int, array<string, mixed>> */
    public array $atRisk = [];

    /** @var array<string, mixed> */
    public array $summary = [];

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
                $this->error = 'Subscription predictions are only available for Pet Heaven brands.';
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

            $data = $bq->getSubscriptionPredictions($brand->name);

            $this->upcoming = $data['upcoming'];
            $this->atRisk = $data['at_risk'];
            $this->summary = $data['summary'];

            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = 'Failed to load predictions: '.$e->getMessage();
            $this->loading = false;
        }
    }

    public function updatedBrandId(): void
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
     * Format date for display.
     */
    public function formatDate(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return '-';
        }

        return date('M j, Y', $timestamp);
    }

    /**
     * Get urgency color class based on days until delivery.
     */
    public function getUrgencyColorClass(int $days): string
    {
        if ($days <= 3) {
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        }
        if ($days <= 7) {
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        }

        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
    }

    /**
     * Get risk reason badge class.
     */
    public function getRiskReasonClass(string $reason): string
    {
        return match ($reason) {
            'Overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'Multiple Skips' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            'Low Engagement' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        };
    }

    /**
     * Check if has upcoming deliveries.
     */
    public function hasUpcoming(): bool
    {
        return ! empty($this->upcoming);
    }

    /**
     * Check if has at-risk subscriptions.
     */
    public function hasAtRisk(): bool
    {
        return ! empty($this->atRisk);
    }

    /**
     * Check if has any data.
     */
    public function hasData(): bool
    {
        return $this->hasUpcoming() || $this->hasAtRisk() || ! empty($this->summary);
    }
}
