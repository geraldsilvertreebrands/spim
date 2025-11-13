<?php

use App\Http\Controllers\ProfileController;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // API endpoint for sync run polling
    Route::get('/admin/api/sync-runs/{syncRun}', function (SyncRun $syncRun) {
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
