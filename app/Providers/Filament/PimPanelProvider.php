<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckUserIsActive;
use App\Http\Middleware\EnsureUserCanAccessPimPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PimPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('pim')
            ->path('pim')
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::hex('#006654'), // FtN green
            ])
            ->brandName('Silvertree PIM')
            ->maxContentWidth('full')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/PimPanel/Resources'), for: 'App\Filament\PimPanel\Resources')
            ->discoverPages(in: app_path('Filament/PimPanel/Pages'), for: 'App\Filament\PimPanel\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/PimPanel/Widgets'), for: 'App\Filament\PimPanel\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->navigationItems([
                NavigationItem::make('Queue Monitor')
                    ->icon('heroicon-o-queue-list')
                    ->url('/horizon')
                    ->openUrlInNewTab()
                    ->group('Settings')
                    ->sort(99)
                    ->visible(fn (): bool => auth()->check() && auth()->user()->hasRole('admin')),

                // Admin panel switching
                NavigationItem::make('Supply Portal')
                    ->icon('heroicon-o-truck')
                    ->url('/supply')
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
                EnsureUserCanAccessPimPanel::class,
            ]);
    }
}
