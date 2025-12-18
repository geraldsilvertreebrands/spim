<?php

namespace App\Providers\Filament;

use App\Filament\PricingPanel\Components\NotificationBadge;
use App\Filament\PricingPanel\Pages\Dashboard;
use App\Http\Middleware\CheckUserIsActive;
use App\Http\Middleware\EnsureUserCanAccessPricingPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class PricingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pricing')
            ->path('pricing')
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::hex('#4f46e5'), // Indigo
            ])
            ->brandName('Pricing Tool')
            ->maxContentWidth('full')
            ->discoverResources(
                in: app_path('Filament/PricingPanel/Resources'),
                for: 'App\Filament\PricingPanel\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/PricingPanel/Pages'),
                for: 'App\Filament\PricingPanel\Pages'
            )
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/PricingPanel/Widgets'),
                for: 'App\Filament\PricingPanel\Widgets'
            )
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationItems([
                // Admin panel switching
                NavigationItem::make('PIM')
                    ->icon('heroicon-o-cube')
                    ->url('/pim')
                    ->group('Switch Panel')
                    ->sort(100)
                    ->visible(fn (): bool => auth()->check() && auth()->user()->hasRole('admin')),

                NavigationItem::make('Supply Portal')
                    ->icon('heroicon-o-truck')
                    ->url('/supply')
                    ->group('Switch Panel')
                    ->sort(101)
                    ->visible(fn (): bool => auth()->check() && auth()->user()->hasRole('admin')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                CheckUserIsActive::class,
                EnsureUserCanAccessPricingPanel::class,
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn () => Blade::render('@livewire(\'pricing-notification-badge\')')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => '<link rel="stylesheet" href="'.asset('css/filament/pricing/responsive.css').'">'
            );
    }

    /**
     * Boot the panel provider.
     */
    public function boot(): void
    {
        Livewire::component('pricing-notification-badge', NotificationBadge::class);
    }
}
