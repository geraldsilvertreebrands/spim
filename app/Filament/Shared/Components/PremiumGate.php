<?php

namespace App\Filament\Shared\Components;

use App\Models\Brand;
use Illuminate\View\Component;

/**
 * Premium Feature Gate Component.
 *
 * Wraps content that should only be accessible to premium users.
 * Shows locked placeholder for basic users with upgrade CTA.
 *
 * Usage:
 * <x-premium-gate feature="Forecasting">
 *     <x-forecasting-chart :data="$data" />
 * </x-premium-gate>
 *
 * With brand context:
 * <x-premium-gate feature="Advanced Analytics" :brand="$brand">
 *     <x-analytics-dashboard />
 * </x-premium-gate>
 */
class PremiumGate extends Component
{
    public string $feature;

    public ?Brand $brand;

    public bool $hasPremiumAccess;

    /**
     * Create a new component instance.
     */
    public function __construct(string $feature, ?Brand $brand = null)
    {
        $this->feature = $feature;
        $this->brand = $brand;

        // Check if user has premium access
        $user = auth()->user();
        $this->hasPremiumAccess = $user ? $user->hasPremiumAccessForBrand($brand) : false;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('filament.shared.components.premium-gate');
    }
}
