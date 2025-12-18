<?php

namespace App\Filament\SupplyPanel\Pages;

use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class PremiumFeatures extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Premium Features';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.supply-panel.pages.premium-features';

    /**
     * Return empty heading to hide the page header.
     */
    public function getHeading(): string
    {
        return '';
    }

    /**
     * Premium features with descriptions.
     *
     * @var array<int, array{name: string, description: string, icon: string}>
     */
    public array $features = [
        [
            'name' => 'Forecasting',
            'description' => 'AI-powered demand forecasting with confidence intervals and scenario modeling. Predict future sales trends and optimize inventory planning.',
            'icon' => 'heroicon-o-chart-bar-square',
        ],
        [
            'name' => 'Cohort Analysis',
            'description' => 'Track customer behavior over time by grouping them into cohorts based on their first purchase date. Understand retention patterns.',
            'icon' => 'heroicon-o-user-group',
        ],
        [
            'name' => 'RFM Segmentation',
            'description' => 'Segment your customers by Recency, Frequency, and Monetary value. Identify your best customers and those at risk of churning.',
            'icon' => 'heroicon-o-squares-2x2',
        ],
        [
            'name' => 'Retention Analytics',
            'description' => 'Deep dive into customer retention metrics. Track churn rates, customer lifetime value, and identify retention drivers.',
            'icon' => 'heroicon-o-arrow-path',
        ],
        [
            'name' => 'Product Deep Dive',
            'description' => 'Comprehensive single-product analysis with all metrics in one place. Understand product performance at the granular level.',
            'icon' => 'heroicon-o-magnifying-glass-plus',
        ],
        [
            'name' => 'Advanced Marketing Analytics',
            'description' => 'Track promotional campaign performance, personalized offer effectiveness, and marketing ROI with detailed analytics.',
            'icon' => 'heroicon-o-megaphone',
        ],
    ];

    public function mount(): RedirectResponse|Redirector|null
    {
        // If user has premium access, redirect to dashboard
        if (auth()->user()->hasPremiumAccess()) {
            return redirect()->route('filament.supply.pages.dashboard');
        }

        return null;
    }

    /**
     * Check if this page should be shown in navigation.
     */
    public static function shouldRegisterNavigation(): bool
    {
        // Only show in navigation for non-premium users
        $user = auth()->user();

        return $user && ! $user->hasPremiumAccess();
    }

    /**
     * Get the contact email for upgrades.
     */
    public function getContactEmail(): string
    {
        return config('app.premium_contact_email', 'premium@silvertreebrands.com');
    }

    /**
     * Get the contact phone for upgrades.
     */
    public function getContactPhone(): string
    {
        return config('app.premium_contact_phone', '+27 21 000 0000');
    }
}
