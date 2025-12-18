<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckUserIsActive;
use App\Http\Middleware\EnsureUserCanAccessSupplyPanel;
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

class SupplyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('supply')
            ->path('supply')
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandName('Supplier Portal')
            ->maxContentWidth('full')
            ->discoverResources(
                in: app_path('Filament/SupplyPanel/Resources'),
                for: 'App\Filament\SupplyPanel\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/SupplyPanel/Pages'),
                for: 'App\Filament\SupplyPanel\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/SupplyPanel/Widgets'),
                for: 'App\Filament\SupplyPanel\Widgets'
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

                NavigationItem::make('Pricing Tool')
                    ->icon('heroicon-o-currency-dollar')
                    ->url('/pricing')
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
                EnsureUserCanAccessSupplyPanel::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('<style>@import url("{{ asset("css/filament/supply/print.css") }}");</style>')
            );
    }
}
