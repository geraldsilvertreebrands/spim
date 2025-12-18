<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'supply-panel-access' => \App\Http\Middleware\EnsureUserCanAccessSupplyPanel::class,
            'pim-panel-access' => \App\Http\Middleware\EnsureUserCanAccessPimPanel::class,
            'pricing-panel-access' => \App\Http\Middleware\EnsureUserCanAccessPricingPanel::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
