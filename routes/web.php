<?php

use App\Http\Controllers\ProfileController;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Route;

// DEBUG: Check auth status
Route::get('/debug-auth', function () {
    $user = auth()->user();
    if (!$user) {
        return 'Not logged in';
    }
    return [
        'email' => $user->email,
        'roles' => $user->roles->pluck('name')->toArray(),
        'is_active' => $user->is_active,
        'hasRole_admin' => $user->hasRole('admin'),
        'hasAnyRole_admin_pim' => $user->hasAnyRole(['admin', 'pim-editor']),
    ];
});

Route::get('/', function () {
    $user = auth()->user();

    // If not authenticated, redirect to PIM login (default)
    if (! $user) {
        return redirect('/pim/login');
    }

    // Redirect based on user's primary role
    if ($user->hasAnyRole(['admin', 'pim-editor'])) {
        return redirect('/pim');
    }

    if ($user->hasAnyRole(['supplier-basic', 'supplier-premium'])) {
        return redirect('/supply');
    }

    if ($user->hasRole('pricing-analyst')) {
        return redirect('/pricing');
    }

    // Fallback to PIM login
    return redirect('/pim/login');
})->name('home');

// Redirect old /admin URLs to /pim
Route::redirect('/admin', '/pim', 301);
Route::redirect('/admin/{any}', '/pim/{any}', 301)->where('any', '.*');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // API endpoint for sync run polling
    Route::get('/pim/api/sync-runs/{syncRun}', function (SyncRun $syncRun) {
        return response()->json([
            'id' => $syncRun->id,
            'status' => $syncRun->status,
            'total_items' => $syncRun->total_items ?? 0,
            'successful_items' => $syncRun->successful_items ?? 0,
            'failed_items' => $syncRun->failed_items ?? 0,
            'skipped_items' => $syncRun->skipped_items ?? 0,
            'completed_at' => $syncRun->completed_at?->format('M j, Y H:i:s'),
            'error_summary' => $syncRun->error_summary,
        ]);
    });
});

require __DIR__.'/auth.php';
