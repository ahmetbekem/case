<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\System\Controllers\AdminSettingsController;
use Presentation\System\Controllers\PublicSettingsController;

// Public ayarlar — frontend boot anında okur (base_currency vs.)
Route::prefix('system')->middleware('throttle:api-read')->group(function () {
    Route::get('/settings/public', [PublicSettingsController::class, 'index']);
});

// Admin yönetimi
Route::prefix('admin/system')->middleware(['jwt.auth', 'role:admin'])->group(function () {
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::patch('/settings/{key}', [AdminSettingsController::class, 'update']);
});
