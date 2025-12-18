<?php

namespace App\Filament\Shared\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Premium feature lock overlay component.
 *
 * Displays a blurred overlay with upgrade messaging for premium-only features.
 * Used in the Supply portal to indicate features not available to basic tier suppliers.
 */
class PremiumLockedPlaceholder extends Component
{
    public function __construct(
        public string $feature = 'this feature',
        public string $contactEmail = 'sales@silvertreebrands.com',
        public string $title = 'Premium Feature',
        public string $description = 'Upgrade to access',
    ) {}

    public function render(): View
    {
        return view('filament.shared.components.premium-locked-placeholder');
    }
}
