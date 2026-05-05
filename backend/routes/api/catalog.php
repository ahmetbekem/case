<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Catalog\Controllers\ProductController;

Route::prefix('catalog')->group(function () {
    // Public read — anonymous browsing of the catalogue
    Route::get('/products',         [ProductController::class, 'index'])->middleware('throttle:api-read');
    Route::get('/products/{id}',    [ProductController::class, 'show'])->middleware('throttle:api-read');

    // Admin mutations — JWT + role:admin
    Route::middleware(['jwt.auth', 'role:admin'])->group(function () {
        Route::post('/products',                  [ProductController::class, 'store']);
        Route::patch('/products/{id}',            [ProductController::class, 'update']);
        Route::delete('/products/{id}',           [ProductController::class, 'destroy']);
        Route::post('/products/{id}/stock',       [ProductController::class, 'adjustStock']);
    });
});
