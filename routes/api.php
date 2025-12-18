<?php

use App\Http\Controllers\Api\SupplyChartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Supply Insights Portal API endpoints
// Note: Using 'auth' middleware (web guard) instead of 'auth:sanctum' since Sanctum is not installed
// These endpoints are designed to be called via AJAX from the same application
// Rate limited to 60 requests per minute per user
Route::middleware(['auth', 'supply-panel-access', 'throttle:60,1'])->prefix('supply')->name('api.supply.')->group(function () {
    // Chart endpoints
    Route::get('/charts/sales-trend', [SupplyChartController::class, 'salesTrend'])->name('charts.sales-trend');
    Route::get('/charts/competitor', [SupplyChartController::class, 'competitorComparison'])->name('charts.competitor');
    Route::get('/charts/market-share', [SupplyChartController::class, 'marketShare'])->name('charts.market-share');

    // Table endpoints
    Route::get('/tables/products', [SupplyChartController::class, 'productsTable'])->name('tables.products');
    Route::get('/tables/stock', [SupplyChartController::class, 'stockTable'])->name('tables.stock');
    Route::get('/tables/purchase-orders', [SupplyChartController::class, 'purchaseOrdersTable'])->name('tables.purchase-orders');
});
